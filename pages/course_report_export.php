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
* This course report export will get information on enrolled users
*
* Each user will have last access, activity completion status and their course completion status
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

//Get course ID from URL
if (!empty($_GET['courseid'])) {
  $courseid=$_GET['courseid'];
}
else{
  exit;
}

$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_url('/local/learninggroup/pages/course_report_export.php');
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add('Course Report Export');
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->fullname);

$course = $DB->get_record('course', array('id'=>$courseid));
//Get course completion info
require_once($CFG->libdir.'/completionlib.php');
$cinfo = new completion_info($course);
function get_last_access($course, $USER){
  global $DB;
  $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', array('courseid' => $course->id, 'userid' => $USER->id));
  return $lastaccess;
}

//Get enrolled users from course
$context = context_course::instance($course->id);
$enrolled_users=get_enrolled_users($context);
$timenow=time();

header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename="Course Report '.$course->fullname.''.date('d m Y',time()).'.csv";');
$file = fopen('php://output', 'w');

//Create array for the csv headers
$headings=array("Firstname","Firstname","Email Address","Roles","Last Accessed");
$criteria=$DB->get_records('course_completion_criteria', array('course'=>$course->id));
$countcriteria=count($criteria);
$i=0;
while($i<$countcriteria){
  $number= $i+1;
  $pusher="Criteria".$number;
  array_push($headings,$pusher);
  $i++;
}
array_push($headings,"Completion State");
fputcsv($file,$headings);
foreach($enrolled_users as $enrolled_user_key => $enrolled_user){
  $user=$DB->get_record('user',array('id'=>$enrolled_user->id));


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

  $rolestr = implode(', ', $rolestr);

  $lastaccess=get_last_access($course, $user);
  if($lastaccess==0){
    $lastaccess='Not Accessed';
  }
  else{
    $lastaccess=date('d m Y',$lastaccess);
  }

  $content=array($user->firstname, $user->lastname, $user->email,$rolestr,$lastaccess);
  $i=0;
  foreach($criteria as $activity){
    $modtype=$activity->module;
    $modinstance=$activity->moduleinstance;
    $criteriaid=$activity->id;
    $params = array(
      'course'        => $course->id,
      'userid'        => $user->id,
      'criteriaid'    => $activity->id,
    );
    $critstatus = new completion_criteria_completion($params);
    if(!isset($critstatus->timecompleted)){
      $compstatus='Not completed';
    }
    else{
      $compstatus='Completed';
    }
    array_push($content,$compstatus);
    $i++;
  }
  $completion=$DB->get_record('course_completions',array('userid'=>$user->id,'course'=>$course->id));
  $status='Not yet started';
  if(isset($completion)){
    if($completion->timestarted ==0){
      $status='Not yet started';
    }
    else if($completion->timestarted >0 && !isset($completion->timecompleted)){
      $status='In Progress';
    }
    else if($completion->timecompleted >=0){
      $status='Completed';
    }
    else{
      $status='Pending';
    }
  }
  array_push($content,$status);
  fputcsv($file,$content);
}
fclose($file);
