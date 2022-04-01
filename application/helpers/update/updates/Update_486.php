<?php

namespace LimeSurvey\Helpers\Update;

class Update_486 extends DatabaseUpdateBase
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        $this->db->createCommand()->addColumn(
            '{{questions}}',
            'same_script',
            "integer NOT NULL default '0'"
        );
    }
}
