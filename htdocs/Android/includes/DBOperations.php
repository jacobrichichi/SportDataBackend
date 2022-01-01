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
                    "itemsSelected" => array("a", "b", "c")
                )
                );*/

            $selectors_array = array();    

            $numSelectors = $req['numSelectors'];

            for($i = 0; $i<$numSelectors; $i++){
                $selectorType = $req['selectorType' . $i];
                $item_array = array();

                $numItems = $req['numItems' . $i];
                for($j = 0; $j < $numItems; $j++){
                    $item_array[] = $req['itemsSelected' . $i . $j];
                }

                $sel_array = array(
                    "selectorType" => $selectorType,
                    "itemsSelected" => $item_array
                );

                $selectors_array[] = $sel_array;
            }
        

            $columns = "";
            $from = "";
            $whereConditions = "";

            if($selectors_array[0]['selectorType'] == "SELECT_TEAM"){
                $columns = "TeamName, Points, Games, Yards, TOs, PassYards, PassTDs, INTs, RushYards, RushTDs";
                $from = "TeamStats";
                $whereConditions = "TeamName = ?";

                $teamName = $selectors_array[0]["itemsSelected"][0];
                
                $sql =  "SELECT " . $columns . " FROM " . $from . " WHERE " . $whereConditions;

                $stmt = $this -> con -> prepare($sql);
    
                $stmt -> bind_param("s", $teamName);
                $stmt -> execute();

                $result = $stmt->get_result();

                $tuples = $result->fetch_all(MYSQLI_ASSOC);

                $data = array("type" => "Team", "tuples" => $tuples);
                
            }

            return $data;


            
        }
    }
?>