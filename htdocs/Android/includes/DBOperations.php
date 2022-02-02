<?php
    class DbOperations{
        private $con;

        function __construct(){
            require_once dirname(__FILE__).'/DbConnect.php';

            $db = new DbConnect();

            $this->con = $db->connect();


        }

        function createTeam($teamName){
            $stmt = $this -> con -> prepare(
                "INSERT INTO `Team` (`TeamName`)
                VALUES(?)"
            );

            $stmt-> bind_param("s", $teamName);

            if($stmt->execute()){
                return true;
            }

            else{
                return false;
            }


        }

        function getAllTeams(){

            $sql = "SELECT TeamName FROM TeamStats";
            $result = mysqli_query($this -> con, $sql);

            $data = $result -> fetch_all(MYSQLI_ASSOC);

            return $data;
        }

        function getCertainPlayers($req){
            $where = "";
            $team = $req['Team'];
            $position = $req['Position'];

            $sql = "SELECT Name FROM PlayerSeasonPassing WHERE ";

            //Only team was passed in as constraint
            if($position == ""){
                $where = "Team = ?";
                $param_types = "ss";
                $params = array($team, $team);
                
            }

            //Only position
            else if($team == ""){
                $where = "Position = ?";
                $param_types = "ss";
                $params = array($position, $position);   
            }
            
            //Both were passed in
            else{
                $where = "Team = ? AND Position = ?";
                $param_types = "ssss";
                $params = array($team, $position, $team, $position);
               
            }

            $sql = $sql . $where . " UNION SELECT Name FROM PlayerSeasonRushReceive WHERE " . $where;
            $stmt = $this -> con -> prepare($sql);
            $stmt -> bind_param($param_types, ...$params);

            $stmt -> execute();

            $result = $stmt->get_result();

            $data = $result->fetch_all(MYSQLI_ASSOC);

            return $data;
        }

        function getPlayersMultipleTeams($req){
            $num_teams = $req['NumTeams'];
            $teams = array();
            for($i = 0; $i<$num_teams; $i++){
                $teams[] = $req["Team" . $i];
            }

            $num_positions = $req['NumPositions'];
            $positions = array();
            for($i = 0; $i<$num_positions; $i++){
                $positions[] = $req["Position" . $i];
            }

            $sql = "SELECT Name FROM PlayerSeasonPassing WHERE";
            $where = "(";
            $param_types = "";
            $params = array();

            for($i = 0; $i<$num_teams; $i++){
                $param_types = $param_types . "ss";
                $params[] = $teams[$i];

                $where = $where . "Team = ? ";
                if($i<$num_teams-1){
                    $where = $where . "OR ";
                }
            }
            $where = $where . ") AND (";

            for($i = 0; $i<$num_positions; $i++){
                $param_types = $param_types . "ss";
                $params[] = $positions[$i];

                $where = $where . "Position = ? ";
                if($i<$num_positions-1){
                    $where = $where . "OR ";
                }
            }
            $where = $where . ")";
            $params = array_merge($params, $params);

            $sql = $sql . $where . " UNION SELECT Name FROM PlayerSeasonRushReceive WHERE " . $where;
            $stmt = $this -> con -> prepare($sql);
            $stmt -> bind_param($param_types, ...$params);

            // echo $sql;
            // echo $params;
            // echo $param_types;

            $stmt -> execute();

            $result = $stmt->get_result();

            $data = $result->fetch_all(MYSQLI_ASSOC);

            return $data;
        }

        function customQuery($req){

           /* $selectors_array = array(
                0 => array(
                    "selectorType" => "select_team",
                    "itemsSelected" => array(array("a", "b"), array("b"), array("c"))
                )
                );*/

            $selectors_array = array();    

            $numSelectors = $req['numSelectors'];

            for($i = 0; $i<$numSelectors; $i++){
                $selectorType = $req['selectorType' . $i];
                $item_lists_array = array();
                $numItemLists = $req['numItemLists' . $i];
                for($j = 0; $j < $numItemLists; $j++){
                    $numItems = $req['numItems' . $i . $j];
                    $items_array = array();
                    for($k = 0; $k < $numItems; $k++){
                        $items_array[] = $req['itemsSelected' . $i . $j . $k];
                    }
                    $item_lists_array[] = $items_array;
                }

                $sel_array = array(
                    "selectorType" => $selectorType,
                    "itemLists" => $item_lists_array
                );


                $selectors_array[] = $sel_array;
            }
        
            /* $selectors_array = array(
                0 => array(
                    "selectorType" => "select_team",
                    "itemsSelected" => array(array("a", "b"), array("b"), array("c"))
                )
                );*/
            
            $data = array();
            $needAnotherSC = false;
            $columns = "DISTINCT P.*, O.TeamName AS OpposingTeamName, I.Week AS Week";
            $from = "PlayerPassingGameLogs P, GameLogsTeamData T, GameLogsTeamData O, GameLogsInfo I, PlayerSnapCountGameLogs S";
            $whereConditions = "T.GameID = O.GameID 
                                AND O.GameID = I.GameID 
                                AND S.GameID = I.GameID
                                AND I.GameID = P.GameID
                                AND S.Name = P.Name";

            $og_params = array();
            $og_param_types = "";
            $current_item_list = $selectors_array[0]["itemLists"][0];

            if($selectors_array[0]['selectorType'] == "SELECT_TEAM"){
                $data["type"] = "Team";
                $teams = $selectors_array[0]['itemLists'][0]; 
                $teams = array_slice($teams, 1);
                $whereConditions = $whereConditions . " AND (" . str_repeat(" (P.TeamName = ? AND T.TeamName = ? AND O.TeamName <> ?) OR ", count($teams) - 1) . " (P.TeamName = ? AND T.TeamName = ? AND O.TeamName <> ?)) ";
                
                $og_params = array();

                // IF TEAMS ARE (Cards, Jets), then params will be (Cards, Cards, Cards, Jets, Jets, Jets)
                for($i = 0; $i< count($teams); $i++){
                    $og_params[] = $teams[$i];
                    $og_params[] = $teams[$i];
                    $og_params[] = $teams[$i];
                }
            }

            else if($selectors_array[0]['selectorType'] == "SELECT_POSITION"){

                $data['type'] = "Position";
                $positions = $selectors_array[0]['itemLists'][0]; 
                $teams = $selectors_array[0]['itemLists'][1];

                $positions = array_slice($positions, 1);
                $teams = array_slice($teams, 1);

                $whereConditions = $whereConditions . " AND (" . str_repeat(" S.Position = ?  OR ", count($positions) - 1) . " S.Position = ?)" 
                            . " AND ("  . str_repeat(" (P.TeamName = ? AND T.TeamName = ? AND O.TeamName <> ?)  OR ", count($teams) - 1) . " (P.TeamName = ? AND T.TeamName = ? AND O.TeamName <> ?))" ;


                $og_params = array();
                for($i = 0; $i< count($positions); $i++){
                    $og_params[] = $positions[$i];
                }

                for($i = 0; $i< count($teams); $i++){
                    $og_params[] = $teams[$i];
                    $og_params[] = $teams[$i];
                    $og_params[] = $teams[$i];
                }
            }

            else{
                $data['type'] = "Player";
                $names = $selectors_array[0]['itemLists'][2]; 
                $names = array_slice($names, 1);
                $whereConditions = $whereConditions . " AND (" . str_repeat(" P.Name = ? OR ", count($names) - 1) . " P.Name = ?) ";
                $og_params = $names;  
            }

            $og_param_types = str_repeat("s", count($og_params));
            $specifiers = "";
            $spec_params = array();
            $spec_param_types = "";

            for($i = 1; $i < count($selectors_array); $i++){
                $specifiers = $specifiers . " AND (";

                if($selectors_array[$i]["selectorType"] == "AGAINST_TEAM"){
                    
                    $other_teams = array_slice($selectors_array[$i]["itemLists"][0], 1);

                    for($j = 0; $j < count($other_teams); $j++){
                        $specifiers = $specifiers . "O.TeamName = ?";
                        $spec_params[] = $other_teams[$j];
                        $spec_param_types = $spec_param_types . "s";
                        if($j < count($other_teams) - 1){
                            $specifiers = $specifiers . " OR ";
                        }
                    }
                    
                }

                else if($selectors_array[$i]["selectorType"] == "TEMPERATURE"){
                    if($selectors_array[$i]["itemLists"][0] == "Less Than"){
                        $specifiers = $specifiers . "I.Temperature < ?";
                    }
                    else{
                        $specifiers = $specifiers . "I.Temperature >= ?";
                    }

                    $spec_params[] = $selectors_array[$i]["itemLists"][1][0];
                    $spec_param_types = $spec_param_types . "s";
                    
                }

                else if($selectors_array[$i]["selectorType"] == "PLAYER_PLAYING"){
                    $needAnotherSC = true;
                    $from = $from . ", PlayerSnapCountGameLogs SB";

                    $whereConditions = $whereConditions . " AND P.GameID = SB.GameID";

                    $other_players = array_slice($selectors_array[$i]["itemLists"][1], 1);
                    for($j = 0; $j < count($other_players); $j++){
                        $specifiers = $specifiers . "(SB.Name = ? AND SB.SnapPercentage > 0)";
                        $spec_params[] = $other_players[$j];
                        $spec_param_types = $spec_param_types . "s";
                        if($j < count($other_players) - 1){
                            $specifiers = $specifiers . " OR ";
                        }
                    }
                }

                else if($selectors_array[$i]["selectorType"] == "PLAYER_ABSENT"){
                    $whereConditions = $whereConditions . "P.GameID = SB.GameID";
                    $needAnotherSC = true;
                    $specifiers = $specifiers . "S.Name <> ? OR (S.Name = ? AND S.SnapPercentage = 0)";
                }

                $specifiers = $specifiers . ")";
            }

            $sql = "SELECT " . $columns . " FROM " . $from . " WHERE " . $whereConditions . $specifiers;

            //echo $sql;

            $stmt = $this -> con -> prepare($sql);

            $params = array_merge($og_params, $spec_params);

            $param_types = $og_param_types . $spec_param_types;

            // for($i = 0; $i<count($params); $i++){
            //     echo " " . $params[$i];
            // }

            // echo "    " . $param_types;

            $stmt -> bind_param($param_types, ...$params);

            $stmt -> execute();

            $result = $stmt->get_result();

            $tuples = $result->fetch_all(MYSQLI_ASSOC);

            $data = array_merge($data, array("playerPassingTuples" => $tuples)); 
            
            //GRAB RUSH RECIEVE

            $columns = str_replace("P.*", "R.*", $columns);
            $from = str_replace("PlayerPassingGameLogs P", "PlayerRushReceiveGameLogs R", $from);
            $whereConditions = str_replace("P.", "R.", $whereConditions);

            $sql = "SELECT " . $columns . " FROM " . $from . " WHERE " . $whereConditions . $specifiers;

            $stmt = $this -> con -> prepare($sql);

            $stmt -> bind_param($param_types, ...$params);

            $stmt -> execute();

            $result = $stmt->get_result();

            $tuples = $result->fetch_all(MYSQLI_ASSOC);

            $data = array_merge($data, array("playerRushReceiveTuples" => $tuples)); 


            if($selectors_array[0]['selectorType'] == "SELECT_TEAM"){
                

                $columns = "DISTINCT I.GameID AS GameID, I.Week AS Week, I.Date AS Date, I.DayOfWeek AS DayOfWeek, I.OT AS OT, I.Temperature AS Temperature,  T.TeamName AS OwnTeamName, T.FirstQuarter AS OwnFirstQuarter, 
                                    T.SecondQuarter AS OwnSecondQuarter, T.ThirdQuarter AS OwnThirdQuarter, T.FourthQuarter as OwnFourthQuarter,
                                    T.OTTotal AS OwnOTTotal, T.TotalScore AS OwnTotalScore, T.Coach AS OwnCoach, T.FirstDowns AS OwnFirstDowns,
                                    T.Penalties AS OwnPenalties, T.PenaltyYards AS OwnPenaltyYards, T.ThirdDownAttempts AS OwnThirdDownAttempts,
                                    T.ThirdDownConversions AS OwnThirdDownConversions, T.FourthDownAttempts AS OwnFourthDownAttempts,
                                    T.FourthDownConversions AS OwnFourthDownConversions, T.ToP AS OwnToP, T.IsAWin AS OwnIsAWin,
                                    O.TeamName AS OppTeamName, O.FirstQuarter AS OppFirstQuarter, 
                                    O.SecondQuarter AS OppSecondQuarter, O.ThirdQuarter AS OppThirdQuarter, O.FourthQuarter as OppFourthQuarter,
                                    O.OTTotal AS OppOTTotal, O.TotalScore AS OppTotalScore, O.Coach AS OppCoach, O.FirstDowns AS OppFirstDowns,
                                    O.Penalties AS OppPenalties, O.PenaltyYards AS OppPenaltyYards, O.ThirdDownAttempts AS OppThirdDownAttempts,
                                    O.ThirdDownConversions AS OppThirdDownConversions, O.FourthDownAttempts AS OppFourthDownAttempts,
                                    O.FourthDownConversions AS OppFourthDownConversions, O.ToP AS OppToP, O.IsAWin AS OppIsAWin";
                
                $from = "GameLogsTeamData T, GameLogsTeamData O, GameLogsInfo I, PlayerSnapCountGameLogs S";
                $whereConditions = "T.GameID = O.GameID 
                                    AND O.GameID = I.GameID 
                                    AND S.GameID = I.GameID";
                if($needAnotherSC){
                    $from = $from . ", PlayerSnapCountGameLogs SB";
                    $whereConditions = $whereConditions . " AND I.GameID = SB.GameID";
                }


                $current_item_list = $selectors_array[0]["itemLists"][0];
                $params = array();
                $param_types = "";

                $teams = $selectors_array[0]['itemLists'][0]; 
                $teams = array_slice($teams, 1); 

                $whereConditions = $whereConditions . " AND (" . str_repeat("(T.TeamName = ? AND O.TeamName <> ?) OR ", count($teams) - 1) . " (T.TeamName = ? AND O.TeamName <> ?))";
                $og_params = array();

                for($i = 0; $i<count($teams); $i++){
                    $og_params[] = $teams[$i];
                    $og_params[] = $teams[$i];
                }

                $og_param_types = str_repeat("s", count($og_params));

                $sql = "SELECT " . $columns . " FROM " . $from . " WHERE " . $whereConditions . $specifiers;

                $stmt = $this -> con -> prepare($sql);

                $params = array_merge($og_params, $spec_params);
                $param_types = $og_param_types . $spec_param_types;

                $stmt -> bind_param($param_types, ...$params);

                $stmt -> execute();

                $result = $stmt->get_result();

                $tuples = $result->fetch_all(MYSQLI_ASSOC);

                $data = array_merge($data, array("teamTuples" => $tuples));
            }

            
            //$data = array("type" => "Team", "tuples" => array());
            return $data;


            
        }
    }
?>