            // Delete duplicate template configurations
            $deleteQuery = "DELETE FROM {{template_configuration}}
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT MIN(id) as id
                            FROM {{template_configuration}} t 
                            GROUP BY t.template_name, t.sid, t.gsid, t.uid
                    ) x
                )";
            $oDB->createCommand($deleteQuery)->execute();
