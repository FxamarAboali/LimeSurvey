<?php

Yii::import('zii.widgets.grid.CGridView');

class CLSGridView extends TbGridView
{
    /**
     * @var string
     */
    public $massiveActionTemplate = '';

    /**
     * Initializes the widget.
     */
    public function init()
    {
        parent::init();
        Yii::app()->clientScript->registerScriptFile(
            Yii::app()->getConfig("extensionsurl") . 'admin/grid/assets/gridScrollbar.js',
            LSYii_ClientScript::POS_BEGIN
        );

        $this->pager = ['class' => 'application.extensions.admin.grid.CLSYiiPager'];
        $this->htmlOptions['class'] = '';
        $classes = array('table', 'table-hover');
        $this->template = "
        <div id=\"bottom-scroller\" class=\"content-right scrolling-wrapper\">
            {items}
         </div>
         <div class=\"row mx-auto mt-4\" id=''>
            <div class=\"col-md-4\" id=\"massive-action-container\">$this->massiveActionTemplate</div>
            <div class=\"col-md-4 \">{pager}</div>
            <div class=\"col-md-4 summary-container\">{summary}</div>
         </div>
        ";

        if (!empty($classes)) {
            $classes = implode(' ', $classes);
            if (isset($this->itemsCssClass)) {
                $this->itemsCssClass .= ' ' . $classes;
            } else {
                $this->itemsCssClass = $classes;
            }
        }
    }

    /**
     * Overwritten because of additional scrollbar at bottom of the gridview itself.
     *
     * @return void
     */

    /**
     * Creates column objects and initializes them.
     */
    protected function initColumns()
    {
        foreach ($this->columns as $i => $column) {
            if (is_array($column) && !isset($column['class'])) {
                $this->columns[$i]['class'] = '\TbDataColumn';
            }
        }
        parent::initColumns();
    }


}
