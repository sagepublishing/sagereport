<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This course report will get information on enrolled users
 *
 * Each user will have last access, activity completion status and their course completion status
 *
 * @package    block_sagereport
 * @category   Blocks
 * @copyright  2018 Alex Noble
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 // config

require_once ('../../../config.php');

// no guest autologin

require_login();
global $DB, $USER;

// check for course id in URL and exit script if not

if (!empty($_GET['courseid']))
	{
	$courseid = $_GET['courseid'];
	$course = $DB->get_record('course', array(
		'id' => $courseid
	));
	}
  else
	{
	echo 'There is no course ID set';
	exit;
	}

// set moodle variables

$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_url('/local/learninggroup/pages/course_report.php');
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add('Course Report');
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->fullname);
echo $OUTPUT->header();

// include completion library

require_once ($CFG->libdir . '/completionlib.php');

$cinfo = new completion_info($course);

// function to get last access

function get_last_access($course, $USER)
	{
	global $DB;
	$lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', array(
		'courseid' => $course->id,
		'userid' => $USER->id
	));
	return $lastaccess;
	}

// echo header for report and export links

echo '<h3>Date: ' . date('d m Y', time()) . '</h3>';
echo 'Running report for ' . $course->fullname . '<br />';
echo '<div class="row">
	<div class="col-lg-12">
		<div class="pull-right">
			<a href="/blocks/sagereport/pages/course_report_export.php?courseid=' . $course->id . '" class="btn btn-primary"><i class="fa fa-download"></i> Export to CSV</a>
		</div>
	</div>
</div>';

$context = context_course::instance($course->id);
$enrolled_users=get_enrolled_users($context);
// check if there is more than one user

if (count($enrolled_users) >= 1)
	{

	// print table headers
echo'<div class="table-responsive">';
	echo '<table class="table">
			<thead>
			<tr>
			<th>Firstname</th>
			<th>Lastname</th>
			<th>Email Address</th>
			<th>Roles</th>
			<th>Last Accessed</th>';

	// get criteria for course
	$criteria = $DB->get_records('course_completion_criteria', array(
		'course' => $course->id
	));
	// count the criteria
	$countcriteria = count($criteria);
	$i = 0;
	//print the headings for each criteria
	while ($i < $countcriteria)
		{
		$number = $i + 1;
		echo '<th>Criteria' . $number . '</th>';
		$i++;
		}
	// print last headers
	echo '
		<th>Completion Status</th>
		</tr>
		</thead>';
	// loop through enrolled users
	foreach($enrolled_users as $enrolled_user_key => $enrolled_user)
		{

      // load full user object
      $user = $DB->get_record('user', array(
        'id' => $enrolled_user->id
      ));

      // Get the roles the user has in the course
  		$rolestr = array();
  		$context = context_course::instance($course->id);
  		$roles = get_user_roles($context, $user->id);
      $role_cancel = false;
      // we only want students and reviewers
  		foreach($roles as $role)
  			{
  			$rolestr[] = role_get_name($role, $context);
        if($role->shortname == 'editingteacher' || $role->shortname == 'manager' || $role->shortname == 'teacher')
        {
          $role_cancel = true;
  			}
      }
      if($role_cancel)
      {
        unset($enrolled_users[$enrolled_user_key]);
        continue;
      }

		// start row
		echo '<tr>';

		// get last access for user and make it human readable
		$lastaccess = get_last_access($course, $user);
		if ($lastaccess == 0)
			{
			$lastaccess = 'Not Accessed';
			}
		else
			{
			$lastaccess = date('d m Y', $lastaccess);
			}
		// print user variables
		echo '<td>' . $user->firstname . '</td>';
		echo '<td>' . $user->lastname . '</td>';
		echo '<td>' . $user->email . '</td>';

		$rolestr = implode(', ', $rolestr);
		echo '<td>' . $rolestr . '</td>';
		// print last access
		echo '<td>' . $lastaccess . '</td>';
		// loop through criteria and print
		$i = 0;
		foreach($criteria as $activity)
			{
			// for each use the completion lib functions to get the current status of each activity and make readable
			$modtype = $activity->module;
			$modinstance = $activity->moduleinstance;
			$criteriaid = $activity->id;
			$params = array(
				'course' => $course->id,
				'userid' => $user->id,
				'criteriaid' => $activity->id,
			);
			$critstatus = new completion_criteria_completion($params);
			if (!isset($critstatus->timecompleted))
				{
				$compstatus = 'Not completed';
				}
			  else
				{
				$compstatus = 'Completed';
				}

			echo '<td>' . $compstatus . '</td>';
			$i++;
			}
		// get course completion for user and make readable
		$completion = $DB->get_record('course_completions', array(
			'userid' => $user->id,
			'course' => $course->id
		));
		$status = 'Not yet started';
		if (isset($completion))
			{
			if ($completion->timestarted == 0)
				{
				$status = 'Not yet started';
				}
			  else
			if ($completion->timestarted > 0 && !isset($completion->timecompleted))
				{
				$status = 'In Progress';
				}
			  else
			if ($completion->timecompleted >= 0)
				{
				$status = 'Completed';
				}
			  else
				{
				$status = 'Pending';
				}
			}

		echo '<td>' . $status . '</td>';
		// end row
		echo '</tr>';
		}
	// end table
	echo '</table></div>';
	}

echo $OUTPUT->footer();
