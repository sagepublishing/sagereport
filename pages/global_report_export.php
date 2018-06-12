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
 * This global report export will get information on courses
 *
 * Each course will have statistics on enrolled users, percentages of completion and how many have accessed the course
 *
 * @package    block_sagereport
 * @category   Blocks
 * @copyright  2018 Alex Noble
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//config
require_once('../../../config.php');

//no guest autologin
require_login();
//must be an unlimited user

global $DB, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/learninggroup/pages/global_report_export.php');
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add('Global Course Report');
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->fullname);
header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="Global Report'.date('d m Y',time()).'.csv";');
    $file = fopen('php://output', 'w');
	fputcsv($file,array('Course Category','Course Name','Course ID','Nr of learners enrolled','% learners accessed courses','Nr of learners that never accessed','% learners complete the course','Nr of learners that have not completed the course'));
//get all courses in scope (active maybe for a particular category)
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
//zero out vars
$total_enrolled=0;
$average_peraccess=0;
$total_noaccess=0;
$average_percompleted=0;
$total_completed=0;

//loop through courses
foreach ($courses as $course) {

    //count enrolled users
    $context = context_course::instance($course->id);
		$enrolled_users = get_enrolled_users($context);

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

    $noaccesscount  = 0;
    foreach ($enrolled_users as $enrolled_user) {
        $user       = $DB->get_record('user', array(
            'id' => $enrolled_user->userid
        ));
        $lastaccess = get_last_access($course, $user);
        if ($lastaccess == 0) {
            $noaccesscount = $noaccesscount + 1;
        } else {
            continue;
        }
    }
    $total_noaccess=$total_noaccess+$noaccesscount;
    $count_enrolled = count($enrolled_users);
	$total_enrolled=$total_enrolled+$count_enrolled;
    if ($count_enrolled == 0) {
        continue;
    }
    //echo course name

    $completed       = $DB->get_records_sql('SELECT * from {course_completions} where timecompleted>0', array());
    $count_completed = count($completed);
	$total_completed=$total_completed+$count_completed;
	$accesscount=$count_enrolled - $noaccesscount;
	$per_accessed = ((($count_enrolled-$noaccesscount) / $count_enrolled) * 100);
	$per_accessed = round($per_accessed, 0, PHP_ROUND_HALF_UP);
	$average_peraccess=$average_peraccess+$per_accessed;
	$per_completed = ($count_completed / $count_enrolled) * 100;
	$per_completed=round($per_completed, 0, PHP_ROUND_HALF_UP);
	$average_percompleted=$average_percompleted+$per_completed;
	$category = $DB->get_record('course_categories',array('id'=>$course->category));
	$content=array(
	$category->name,
	$course->fullname,
	$course->id,
	$count_enrolled,
	$per_accessed,
    $noaccesscount,
	$per_completed,
    $count_completed);
	fputcsv($file,$content);
}
$count_courses=count($courses);
$average_percompleted=$average_percompleted/$count_courses;
$average_peraccess = ((($total_enrolled-$total_noaccess) / $total_enrolled) * 100);
$average_peraccess= round($average_peraccess, 0, PHP_ROUND_HALF_UP);
$footer=array(
'',
'Total courses: '.$count_courses,
'',
'Total enrolled: '.$total_enrolled,
'Average % not accessed: '.$average_peraccess,
'Total not accessed: '.$total_noaccess,
'Average % completion: '.$average_percompleted,
'Total completed: '.$total_completed);
fputcsv($file,$footer);
