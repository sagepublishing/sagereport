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
 * This global report will get information on courses
 *
 * Each course will have statistics on enrolled users, percentages of completion and how many have accessed the course
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

// set moodle headers

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/learninggroup/pages/global_report.php');
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add('Global Course Report');
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->fullname);
echo $OUTPUT->header();

// get all courses in scope (active maybe for a particular category)

$courses = $DB->get_records_sql('SELECT * from {course} where id !=1', array());

function get_last_access($course, $USER)
	{
	global $DB;
	$lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', array(
		'courseid' => $course->id,
		'userid' => $USER->id
	));
	return $lastaccess;
	}

// zero out vars

$total_enrolled = 0;
$average_peraccess = 0;
$total_noaccess = 0;
$average_percompleted = 0;
$total_completed = 0;

// echo page headers and export

echo '<h3>Date: ' . date('d m Y', time()) . '</h3>';
echo 'Running global report';
echo '<div class="row">
	<div class="col-lg-12">
		<div class="pull-right">
			<a href="/blocks/sagereport/pages/global_report_export.php" class="btn btn-primary"><i class="fa fa-download"></i> Export to CSV</a>
		</div>
	</div>
</div>';
echo '<br />';

// start table

echo '<table>';

// print table headers

echo '<tr>
<th>Category Name</th>
<th>Course Name</th>
<th>Course ID</th>
<th>Nr of learners enrolled</th>
<th>% learners accessed course</th>
<th>Nr of learners that never accessed</th>
<th>% learners completed the course</th>
<th>Nr of learners that have not completed the course</th>
</tr>';

// loop through courses

foreach($courses as $course)
	{

		$context = context_course::instance($course->id);
		$enrolled_users=get_enrolled_users($context);

		//we only want students and reviewers, so we need the Roles
		foreach($enrolled_users as $enrolled_user_key => $enrolled_user)
		{
			// load full user object
			$user = $DB->get_record('user', array(
				'id' => $enrolled_user->id
			));
			$roles = get_user_roles($context, $user->id);
			foreach($roles as $role)
			{
				if($role->shortname == 'editingteacher' || $role->shortname == 'manager' || $role->shortname == 'teacher')
				{
					unset($enrolled_users[$enrolled_user_key]);
				}
			}
		}

	// zero out enrolled users

	$noaccesscount = 0;

	// find how many enrolled users have not accessed the course

	foreach($enrolled_users as $enrolled_user)
		{
		$user = $DB->get_record('user', array(
			'id' => $enrolled_user->id
		));
		$lastaccess = get_last_access($course, $user);
		if ($lastaccess == 0)
			{
			$noaccesscount = $noaccesscount + 1;
			}
		  else
			{
			continue;
			}
		}

	// amend global total who have not accessed

	$total_noaccess = $total_noaccess + $noaccesscount;

	// get number of enrolled users

	$count_enrolled = count($enrolled_users);

	// amend global average enrolled

	$total_enrolled = $total_enrolled + $count_enrolled;

	// if count is 0 then skip course as we only want courses with enrolments

	if ($count_enrolled == 0)
		{
		continue;
		}

	// echo course name

	$category = $DB->get_record('course_categories', array(
		'id' => $course->category
	));

	// print category name

	echo '<td>' . $category->name . '</td>';

	// print link to course

	echo '<td><a href="/enrol/users.php?id=' . $course->id . '">' . $course->fullname . '</a></td>';

	// print course id

	echo '<td>' . $course->id . '</td>';

	// Get completion information for course

	$completed = $DB->get_records_sql('SELECT * from {course_completions} where timecompleted>0', array());
	$count_completed = count($completed);

	// amend global total users completed

	$total_completed = $total_completed + $count_completed;
	$accesscount = $count_enrolled - $noaccesscount;

	// calculate percentage accessed

	$per_accessed = ((($count_enrolled - $noaccesscount) / $count_enrolled) * 100);
	$per_accessed = round($per_accessed, 0, PHP_ROUND_HALF_UP);

	// amend average percentage access

	$average_peraccess = $average_peraccess + $per_accessed;

	// calculate percentage completed

	$per_completed = ($count_completed / $count_enrolled) * 100;
	$per_completed = round($per_completed, 0, PHP_ROUND_HALF_UP);

	// amend average percentage completed

	$average_percompleted = $average_percompleted + $per_completed;

	// print number of enrolled users

	echo '<td>' . $count_enrolled . '</td>';

	// count users who have never accessed courses and use as percentage

	echo '<td>' . $per_accessed . '</td>';

	// display users who have never accessed course

	echo '<td>' . $noaccesscount . '</td>';

	// Count users who have completed the course and use for percentage (based on enrolled users)

	echo '<td>' . $per_completed . '</td>';

	// display number of users who have no complete course

	echo '<td>' . $count_completed . '</td>';
	echo '</tr>';
	}

// find number of courses so overall averages can be made.

$count_courses = count($courses);
$average_percompleted = $average_percompleted / $count_courses;
$average_peraccess = ((($total_enrolled - $total_noaccess) / $total_enrolled) * 100);
$average_peraccess = round($average_peraccess, 0, PHP_ROUND_HALF_UP);

// Print footer of table with data where needed

echo '<tr>';
echo '<td></td>';
echo '<td>Total courses: ' . $count_courses . '</td>';
echo '<td></td>';
echo '<td>Total enrolled: ' . $total_enrolled . '</td>';
echo '<td>Average % not accessed: ' . $average_peraccess . '</td>';
echo '<td>Total not accessed: ' . $total_noaccess . '</td>';
echo '<td>Average % completion: ' . $average_percompleted . '</td>';
echo '<td>Total completed: ' . $total_completed . '</td>';
echo '</tr>';

// end table

echo '</table>';
echo $OUTPUT->footer();
