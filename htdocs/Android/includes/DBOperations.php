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

                /*for($i = 0; $i < count($sel_array['itemLists']); $i++){
                    for($j = 0; j<count($sel_array['itemLists'][$i]); i++){
                        echo($sel_array['itemLists'][$i][$j])
                    }
                }*/
            }
        

            $columns = "";
            $from = "";
            $whereConditions = "";
            /* $selectors_array = array(
                0 => array(
                    "selectorType" => "select_team",
                    "itemsSelected" => array(array("a", "b"), array("b"), array("c"))
                )
                );*/

            if($selectors_array[0]['selectorType'] == "SELECT_TEAM"){
                $columns = "TeamName, Points, Games, Yards, TOs, PassYards, PassTDs, INTs, RushYards, RushTDs";
                $from = "TeamStats";
                $whereConditions = "";

                $current_item_list = $selectors_array[0]['itemLists'][0];
                $teamNames = array();
                $param_types = "";


                for($i = 0; $i < count($current_item_list); $i++){
                    $whereConditions = $whereConditions . " TeamName = ? ";
                    $teamNames[] = $current_item_list[$i];
                    $param_types = $param_types . "s";

                    if($i != count($current_item_list) - 1){
                        $whereConditions = $whereConditions . " OR ";
                    } 


                }
                
                $sql =  "SELECT " . $columns . " FROM " . $from . " WHERE " . $whereConditions;

                $stmt = $this -> con -> prepare($sql);
    
                $stmt -> bind_param($param_types, ...$teamNames);

                $stmt -> execute();

                $result = $stmt->get_result();

                $tuples = $result->fetch_all(MYSQLI_ASSOC);

                $data = array("type" => "Team", "tuples" => $tuples);
                
            }
            //$data = array("type" => "Team", "tuples" => array());
            return $data;


            
        }
    }
?>