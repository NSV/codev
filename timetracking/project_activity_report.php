<?php
require('../include/session.inc.php');

/*
   This file is part of CoDev-Timetracking.

   CoDev-Timetracking is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   CoDev-Timetracking is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with CoDev-Timetracking.  If not, see <http://www.gnu.org/licenses/>.
*/

require('../path.inc.php');

class ProjectActivityReportController extends Controller {

   /**
    * Initialize complex static variables
    * @static
    */
   public static function staticInit() {
      // Nothing special
   }

   protected function display() {
      if(Tools::isConnectedUser()) {
         // team
         $user = UserCache::getInstance()->getUser($_SESSION['userid']);
         $teamList = $user->getTeamList();

         if (count($teamList) > 0) {
            // use the teamid set in the form, if not defined (first page call) use session teamid
            $teamid = 0;
            if (isset($_POST['teamid'])) {
               $teamid = Tools::getSecurePOSTIntValue('teamid');
               $_SESSION['teamid'] = $teamid;
            } elseif (isset($_SESSION['teamid'])) {
               $teamid = $_SESSION['teamid'];
            }

            // dates
            $weekDates = Tools::week_dates(date('W'),date('Y'));
            $startdate = Tools::getSecurePOSTStringValue("startdate",Tools::formatDate("%Y-%m-%d",$weekDates[1]));
            $this->smartyHelper->assign('startDate', $startdate);

            $enddate = Tools::getSecurePOSTStringValue("enddate",Tools::formatDate("%Y-%m-%d",$weekDates[5]));
            $this->smartyHelper->assign('endDate', $enddate);

            $this->smartyHelper->assign('teams', SmartyTools::getSmartyArray($teamList,$teamid));

            $isDetailed = Tools::getSecurePOSTStringValue('cb_detailed','');
            $this->smartyHelper->assign('isDetailed', $isDetailed);

            if (isset($_POST['teamid']) && array_key_exists($teamid, $teamList)) {
               $startTimestamp = Tools::date2timestamp($startdate);
               $endTimestamp = Tools::date2timestamp($enddate);
               $timeTracking = new TimeTracking($startTimestamp, $endTimestamp, $teamid);

               $this->smartyHelper->assign('projectActivityReport', $this->getProjectActivityReport($timeTracking->getProjectTracks(true), $teamid, $isDetailed));
            }
         }
      }
   }

   /**
    * Get project activity report
    * @param mixed[][][] $projectTracks
    * @param int $teamid The team id
    * @param boolean $isDetailed
    * @return mixed[]
    */
   private function getProjectActivityReport(array $projectTracks, $teamid, $isDetailed) {
      $team = TeamCache::getInstance()->getTeam($teamid);
      $projectActivityReport = NULL;
      foreach ($projectTracks as $projectId => $bugList) {
         $project = ProjectCache::getInstance()->getProject($projectId);

         $jobList = $project->getJobList($team->getProjectType($projectId));
         $jobTypeList = array();
         if ($isDetailed) {
            foreach($jobList as $jobId => $jobName) {
               $jobTypeList[$jobId] = $jobName;
            }
         }

         // write table content (by bugid)
         $row_id = 0;
         $bugDetailedList = "";
         foreach ($bugList as $bugid => $jobs) {
            $issue = IssueCache::getInstance()->getIssue($bugid);
            $totalTime = 0;
            $tr_class = ($row_id & 1) ? "row_even" : "row_odd";

            $subJobList = array();
            foreach($jobList as $jobId => $jobName) {
               $jobTime = 0;
               if(array_key_exists($jobId, $jobs)) {
                  $jobTime = $jobs[$jobId];
               }
               if ($isDetailed) {
                  $subJobList[$jobId] = $jobTime;
               }
               $totalTime += $jobTime;
            }

            $row_id += 1;

            $bugDetailedList[$bugid] = array(
               'class' => $tr_class,
               'description' => SmartyTools::getIssueDescription($bugid, $issue->getTcId(), $issue->getSummary()),
               'jobList' => $subJobList,
               'targetVersion' => $issue->getTargetVersion(),
               'currentStatusName' => $issue->getCurrentStatusName(),
               'progress' => round(100 * $issue->getProgress()),
               'backlog' => $issue->getBacklog(),
               'totalTime' => $totalTime,
            );
         }

         $projectActivityReport[$projectId] = array(
            'id' => $projectId,
            'name' => $project->getName(),
            'jobList' => $jobTypeList,
            'bugList' => $bugDetailedList
         );
      }

      return $projectActivityReport;
   }

}

// ========== MAIN ===========
ProjectActivityReportController::staticInit();
$controller = new ProjectActivityReportController('Weekly activities','TimeTracking');
$controller->execute();

?>
