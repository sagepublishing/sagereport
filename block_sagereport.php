<?php

class block_sagereport extends block_base {

    public function init() {
        $this->title = get_string('menuname', 'block_sagereport');
    }

    public function specialization() {
		
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
		//adding in ID
        global $DB, $COURSE, $CFG, $USER;
        $intro = '';
		$intro='
		<a class="btn btn-primary" href="/blocks/sagereport/pages/course_report.php?courseid='.$COURSE->id.'">Course Report</a>
		<a class="btn btn-primary" href="/blocks/sagereport/pages/global_report.php">Global Report</a>';

        $this->content = new stdClass;
        $this->content->text = $intro;
        $this->content->footer = '';

        return $this->content;
    }

}