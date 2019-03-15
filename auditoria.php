<?php
    class DataBase{
        public function GetConnection(){
            try{
                $conn = new \PDO("mysql:host= ;dbname= ", '', '');
                $conn->exec("set names utf8");
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return $conn;
            } catch(\PDOException $e){
                throw new \Exception($e->getMessage());
            }
        }
    }

    class Auditoria extends DataBase{
        private $db;
        private $table;
        private $schema;
        public function __construct($table, $schema){
            $this->db = new DataBase();
            $this->db = $this->db->GetConnection();
            $this->db->beginTransaction();

            $this->table = $table;
            $this->schema = $schema;
        }

        public function Audit(){
            try{
                $this->DataBase();
            }catch(Exception $ex){
                echo $ex->getMessage();
            }
        }

        private function DataBase(){
            try{
                $sql = $this->db->prepare("SELECT 
                                                COUNT(*) verifica_schema
                                            FROM
                                                INFORMATION_SCHEMA.SCHEMATA X
                                            WHERE
                                                X.SCHEMA_NAME = :schema");
                $sql->bindParam(':schema', $this->schema, PDO::PARAM_STR);
                $sql->execute();
                $verifica_schema = $sql->fetch(PDO::FETCH_ASSOC);
                if($verifica_schema['verifica_schema'] = 0){
                    throw new Exception('Database não localizado.'); 
                } else {
                    $this->Table();
                }
            }catch(Exception $ex){
                throw new Exception($ex->getMessage());
            }
        }

        private function Table(){
            try{
                $sql = $this->db->prepare("SELECT 
                                                COUNT(*) verifica_tabela
                                            FROM
                                                INFORMATION_SCHEMA.TABLES x
                                            WHERE
                                                x.table_schema = :schema
                                            AND x.table_name = :table");
                $sql->bindParam(':schema', $this->schema, PDO::PARAM_STR);
                $sql->bindParam(':table', $this->table, PDO::PARAM_STR);
                $sql->execute();
                $dados_verifica = $sql->fetch(PDO::FETCH_ASSOC);
                if($dados_verifica['verifica_tabela'] == 0){
                    throw new Exception('Tabela não encontrada no banco de dados!');
                } else {
                    $this->Columns();
                }
                
            }catch(Exception $ex){
                throw new Exception($ex->getMessage());
            }
        }

        private function Columns(){
            try{
                $sql = $this->db->prepare("SELECT 
                                                x.column_name,
                                                x.character_set_name,
                                                x.collation_name,
                                                x.column_type
                                            FROM
                                                (SELECT 
                                                    y.column_name column_name,
                                                        y.character_set_name character_set_name,
                                                        y.collation_name collation_name,
                                                        y.column_type column_type,
                                                        y.ordinal_position ordinal_position
                                                FROM
                                                    INFORMATION_SCHEMA.COLUMNS y
                                                WHERE
                                                    table_schema = :schema
                                                        AND table_name = :table UNION ALL SELECT 
                                                    'action' column_name,
                                                        NULL character_set_name,
                                                        NULL collation_name,
                                                        'varchar(50)' column_type,
                                                        0 ordinal_position
                                                UNION ALL SELECT 
                                                    'date' column_name,
                                                        NULL character_set_name,
                                                        NULL collation_name,
                                                        'varchar(50)' column_type,
                                                        0 ordinal_position
                                                ) x
                                            ORDER BY ordinal_position");
                $sql->bindParam(':schema', $this->schema, PDO::PARAM_STR);
                $sql->bindParam(':table', $this->table, PDO::PARAM_STR);
                $sql->execute();
                while($data = $sql->fetch(PDO::FETCH_ASSOC)){
                    $elements[] = array('coluna' => 'old_'.$data['column_name'],
                                        'tipo_coluna' => $data['column_type'],
                                        'coluna_normal' => $data['column_name']);
                }
                $this->CreateAudit($elements);
            }catch(Exception $ex){
                throw new Exception($ex->getMessage());
            }
        }

        private function CreateAudit($elements){
            try{
                $create = 'CREATE TABLE '.$this->schema.'.'.$this->table.'_audit (';
                $total = count($elements);
                $query = 'INSERT INTO '.$this->table.'_audit (';
                for($i=0; $i < count($elements); $i++){
                    if($i == 0){
                        if($elements[$i]['coluna_normal'] == 'action'){
                            $query_values_update = "'UPDATE'";
                            $query_values_insert = "'INSERT'";
                            $query_values_delete = "'DELETE'";
                        } else if($elements[$i]['coluna_normal'] == 'date'){
                            $query_values_update = 'now()';
                            $query_values_insert = 'now()';
                            $query_values_delete = 'now()';
                        } else {
                            $query_values_update = $elements[$i]['coluna_normal'];
                            $query_values_insert = $elements[$i]['coluna_normal'];
                            $query_values_delete = $elements[$i]['coluna_normal'];
                        }

                        $columns = $elements[$i]['coluna'].' '.$elements[$i]['tipo_coluna']. ' null';
                        $query = 'INSERT INTO '.$this->table.'_audit ('.$elements[$i]['coluna'];
                    } else {
                        $columns .= $elements[$i]['coluna'].' '.$elements[$i]['tipo_coluna']. ' null';
                        $query .= ''.$elements[$i]['coluna'];
                        if($elements[$i]['coluna_normal'] == 'action'){
                            $query_values_update .= "'UPDATE'";
                            $query_values_insert .= "'INSERT'";
                            $query_values_delete .= "'DELETE'";
                        } else if($elements[$i]['coluna_normal'] == 'date'){
                            $query_values_update .= 'now()';
                            $query_values_insert .= 'now()';
                            $query_values_delete .= 'now()';
                        } else {
                            $query_values_update .= 'old.'.$elements[$i]['coluna_normal'];
                            $query_values_insert .= 'new.'.$elements[$i]['coluna_normal'];
                            $query_values_delete .= 'old.'.$elements[$i]['coluna_normal'];
                        }
                    }

                    if($i + 1 == $total){
                        $columns.= '<br />); <br /><br />';
                        $query_values_update .= '); <br />';
                        $query_values_insert .= '); <br />';
                        $query_values_delete .= '); <br />';
                        $query .= ') <br /> VALUES (';
                    } else {
                        $columns.=', <br />';
                        $query_values_update .= ', <br />';
                        $query_values_insert .= ', <br />';
                        $query_values_delete .= ', <br />';
                        $query .= ', <br />';
                    }
                }
                $create .= $columns;
                echo $create;
                $this->CreateTrigger($query.$query_values_insert, $query.$query_values_update, $query.$query_values_delete);
            }catch(Exception $ex){
                throw new Exception($ex->getMessage());
            }
        }

        private function CreateTrigger($query_insert, $query_update, $query_delete){
            try{
                $trigger_insert = 'DROP TRIGGER IF EXISTS `'.$this->schema.'`.`'.$this->table.'_AFTER_INSERT`; <br /> DELIMITER $$ <br />  USE `'.$this->schema.'`$$ <br /> CREATE DEFINER = CURRENT_USER TRIGGER `'.$this->schema.'`.`'.$this->table.'_AFTER_INSERT` AFTER INSERT ON `'.$this->table.'` FOR EACH ROW
                BEGIN <br />'.$query_insert.'END; <br /> END;$$ <br /> DELIMITER ;';
                echo $trigger_insert.'<br /><br />';

                $trigger_update = 'DROP TRIGGER IF EXISTS `'.$this->schema.'`.`'.$this->table.'_AFTER_UPDATE`; <br /> DELIMITER $$ <br />  USE `'.$this->schema.'`$$ <br /> CREATE DEFINER = CURRENT_USER TRIGGER `'.$this->schema.'`.`'.$this->table.'_AFTER_UPDATE` AFTER UPDATE ON `'.$this->table.'` FOR EACH ROW
                BEGIN <br />'.$query_update.'END; <br /> END;$$ <br /> DELIMITER ;';
                echo $trigger_update.'<br /><br />';

                $trigger_delete = 'DROP TRIGGER IF EXISTS `'.$this->schema.'`.`'.$this->table.'_AFTER_DELETE`; <br /> DELIMITER $$ <br />  USE `'.$this->schema.'`$$ <br /> CREATE DEFINER = CURRENT_USER TRIGGER `'.$this->schema.'`.`'.$this->table.'_AFTER_DELETE` AFTER DELETE ON `'.$this->table.'` FOR EACH ROW
                BEGIN <br />'.$query_delete.'END; <br /> END;$$ <br /> DELIMITER ;';
                echo $trigger_delete.'<br />';
            }catch(Exception $ex){
                throw new Exception($ex->getMessage());
            }
        }
    }

    $audit = new Auditoria('pessoas', 'nota_desenv');
    $audit->Audit();
?>