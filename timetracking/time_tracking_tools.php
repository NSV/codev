<?php if (!isset($_SESSION)) { session_start(); } ?>

<?php include_once '../path.inc.php'; ?>

<?php
include_once "user.class.php";


function displayCheckWarnings($userid, $team_id = NULL, $isStrictlyTimestamp = FALSE) {
   // 2010-05-31 is the first date of use of this tool
   $user1 = new User($userid);

   $startTimestamp = $user1->getArrivalDate($team_id);
   $endTimestamp   = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
   $timeTracking   = new TimeTracking($startTimestamp, $endTimestamp, $team_id);

   $incompleteDays = $timeTracking->checkCompleteDays($userid, $isStrictlyTimestamp);

   echo "<p>\n";
   foreach ($incompleteDays as $date => $value) {
      $formatedDate = date("Y-m-d", $date);
      $color = ($date >= ($endTimestamp + (24 * 60 * 60))) ? "blue": "red"; // tomorow is blue
      if ($value < 1) {
        echo "<br/><span style='color:$color' width='70'>$formatedDate ".T_("incomplete (missing ").(1-$value)." ".T_("days").")</span>\n";
      } else {
        echo "<br/><span style='color:$color' width='70'>$formatedDate ".T_("inconsistent")." (".($value)." ".T_("days").")</span>\n";
      }
   }

   $missingDays = $timeTracking->checkMissingDays($userid);
   foreach ($missingDays as $date) {
      $formatedDate = date("Y-m-d", $date);
      echo "<br/><span style='color:red' width='70'>$formatedDate ".T_("not defined.")."</span>\n";
   }
}

function displayTimetrackingTuples($userid, $startTimestamp=NULL, $endTimestamp=NULL) {
   // Display previous entries
   echo "<div align='center'>\n";
   echo "<table>\n";
   echo "<caption>".T_("Imputations")."</caption>\n";
   echo "<tr>\n";
   echo "<th></th>\n";
   echo "<th>".T_("Date")."</th>\n";
   echo "<th>Mantis</th>\n";
   echo "<th>".T_("TC Issue")."</th>\n";
   echo "<th>".T_("Duration")."</th>\n";
   echo "<th>".T_("Project")."</th>\n";
   echo "<th>".T_("Description")."</th>\n";
   echo "<th>".T_("Job")."</th>\n";
   echo "<th>".T_("Category")."</th>\n";
   echo "<th>".T_("Status")."</th>\n";
   echo "<th title='BI + BS'>".T_("Load Estimation")."</th>\n";
   echo "<th title='".T_("Remaining")."'>".T_("RAE")."</th>\n";
   echo "</tr>\n";

   $query     = "SELECT id, bugid, jobid, date, duration ".
                "FROM `codev_timetracking_table` ".
                "WHERE userid=$userid ";

   if (NULL != $startTimestamp) { $query .= "AND date >= $startTimestamp "; }
   if (NULL != $endTimestamp)   { $query .= "AND date <= $endTimestamp "; }
   $query .= "ORDER BY date DESC";
   $result    = mysql_query($query) or die("Query failed: $query");
   while($row = mysql_fetch_object($result))
   {
      // get information on this bug
      $query2  = "SELECT summary, status, date_submitted, project_id, category_id FROM `mantis_bug_table` WHERE id=$row->bugid";
      $result2 = mysql_query($query2) or die("Query failed: $query2");
      $row2 = mysql_fetch_object($result2);
      $issue = IssueCache::getInstance()->getIssue($row->bugid);

      // get general information
      $query3  = "SELECT name FROM `codev_job_table` WHERE id=$row->jobid";
      $result3 = mysql_query($query3) or die("Query failed: $query3");
      $jobName = mysql_result($result3, 0);
      $formatedDate= date("Y-m-d", $row->date);
      $cosmeticDate    = date("Y-m-d (l)", $row->date);
      $formatedJobName = str_replace("'", "\'", $jobName);
      $formatedSummary = str_replace("'", "\'", $issue->summary);
      $formatedSummary = str_replace('"', "\'", $formatedSummary);
      $trackDescription = "$formatedDate | $row->bugid ($issue->tcId) | $formatedJobName | $row->duration | $formatedSummary";

      $totalEstim = $issue->effortEstim + $issue->effortAdd;

      echo "<tr>\n";
      //echo "<td width=40>\n";
      echo "<td>\n";
      echo "<a title='".T_("delete this row")."' href=\"javascript: deleteTrack('".$row->id."', '".$trackDescription."', '".$row->bugid."')\" ><img border='0' src='b_drop.png'></a>\n";
      echo "</td>\n";
      echo "<td width=170>".$cosmeticDate."</td>\n";
      echo "<td>".mantisIssueURL($row->bugid)."</td>\n";
      echo "<td>".$issue->tcId."</td>\n";
      echo "<td>".$row->duration."</td>\n";
      echo "<td>".$issue->getProjectName()."</td>\n";
      echo "<td>".$issue->summary."</td>\n";
      echo "<td>".$jobName."</td>\n";
      echo "<td>".$issue->getCategoryName()."</td>\n";
      echo "<td>".$issue->getCurrentStatusName()."</td>\n";
      echo "<td title='$issue->effortEstim + $issue->effortAdd'>".$totalEstim."</td>\n";
      echo "<td>".$issue->remaining."</td>\n";

      echo "</tr>\n";
   }
   echo "</table>\n";
   echo "<div>\n";
}

function displayWeekDetails($weekid, $weekDates, $userid, $timeTracking, $curYear=NULL) {

	if (NULL == $curYear) { $curYear = date('Y'); }

	echo "<div align='center'>\n";
   echo "<br/>".T_("Week")." \n";
   echo "<select id='weekidSelector' name='weekidSelector' onchange='javascript: submitWeekid()'>\n";
   for ($i = 1; $i <= 53; $i++)
   {
      $wDates      = week_dates($i,$curYear);

      if ($i == $weekid) {
        echo "<option selected value='".$i."'>W".$i." | ".date("d M", $wDates[1])." - ".date("d M", $wDates[5])."</option>\n";
      } else {
        echo "<option value='".$i."'>W".$i." | ".date("d M", $wDates[1])." - ".date("d M", $wDates[5])."</option>\n";
      }
   }
   echo "</select>\n";
  echo "<select id='yearSelector' name='yearSelector' onchange='javascript: submitWeekid()'>\n";
  for ($y = ($curYear -1); $y <= ($curYear +1); $y++) {

    if ($y == $curYear) {
      echo "<option selected value='".$y."'>".$y."</option>\n";
    } else {
      echo "<option value='".$y."'>".$y."</option>\n";
    }
  }
  echo "</select>\n";

   $weekTracks = $timeTracking->getWeekDetails($userid);
   echo "<table>\n";
   echo "<tr>\n";
   echo "<th>".T_("Task")."</th>\n";
   echo "<th>".T_("RAE")."</th>\n";
   echo "<th>".T_("Job")."</th>\n";
   echo "<th width='80'>".T_("Monday")."<br/>".date("d M", $weekDates[1])."</th>\n";
   echo "<th width='80'>".T_("Tuesday")."<br/>".date("d M", $weekDates[2])."</th>\n";
   echo "<th width='80'>".T_("Wednesday")."<br/>".date("d M", $weekDates[3])."</th>\n";
   echo "<th width='80'>".T_("Thursday")."<br/>".date("d M", $weekDates[4])."</th>\n";
   echo "<th width='80'>".T_("Friday")."<br/>".date("d M", $weekDates[5])."</th>\n";
   echo "</tr>\n";
   foreach ($weekTracks as $bugid => $jobList) {
      $issue = IssueCache::getInstance()->getIssue($bugid);
      foreach ($jobList as $jobid => $dayList) {

         $query3  = "SELECT name FROM `codev_job_table` WHERE id=$jobid";
         $result3 = mysql_query($query3) or die("Query failed: $query3");
         $jobName = mysql_result($result3, 0);

         echo "<tr>\n";
         echo "<td>".mantisIssueURL($bugid)." / ".$issue->tcId." : ".$issue->summary."</td>\n";
         echo "<td>".$issue->remaining."</td>\n";
         echo "<td>".$jobName."</td>\n";
         for ($i = 1; $i <= 5; $i++) {
            echo "<td>".$dayList[$i]."</td>\n";
         }
         echo "</tr>\n";
      }
   }
   echo " </table>\n";
   echo "</div>\n";
}

?>