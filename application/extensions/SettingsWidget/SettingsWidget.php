<?php

    class SettingsWidget extends CWidget
    {
        protected static $counter = 0;
        
        public $action;
        /**
         *
         * @var array Buttons for the form.
         */
        public $buttons = array();

        /**
         * Set to false to render elements in an existing form.
         * @var boolean
         */
        public $form = true;
        public $formHtmlOptions = array();
        public $method = 'post';
        public $prefix;
        public $settings = array();

        public $title;


        public function beginForm()
        {
            if ($this->form)
            {
                echo CHtml::beginForm($this->action, $this->method, $this->formHtmlOptions);
            }
            else
            {
                echo CHtml::openTag('div', array('class' => 'settingswidget', 'id' => $this->getId()));
            }
            if (isset($this->title))
            {
                echo CHtml::tag('legend', array(), $this->title);
            }
        }

        public function endForm()
        {
            if ($this->form)
            {
                echo CHtml::endForm();
            }
            else
            {
                echo CHtml::closeTag('div');
            }
        }

        public function init() {
            parent::init();

            // Register assets.
            Yii::app()->getClientScript()->registerPackage('jquery');
            Yii::app()->getClientScript()->registerCssFile(App()->getAssetManager()->publish(dirname(__FILE__) . '/assets/settingswidget.css'));
            Yii::app()->getClientScript()->registerScriptFile(App()->getAssetManager()->publish(dirname(__FILE__) . '/assets/settingswidget.js'));

            // Add default form class.
            $this->formHtmlOptions['class'] = isset($this->formHtmlOptions['class']) ? $this->formHtmlOptions['class'] . " settingswidget" : 'settingswidget';


            // Start form
            $this->beginForm();
        }

        protected function renderButton($label, $htmlOptions)
        {
            if (is_string($htmlOptions))
            {
                $label = $htmlOptions;
                $htmlOptions = array();
            }
			if (isset($htmlOptions['type']) && $htmlOptions['type'] == 'link')
			{
				$htmlOptions['class'] = 'limebutton';
				echo CHtml::linkButton($label, $htmlOptions);
			}
			else
			{
				echo CHtml::submitButton($label, $htmlOptions);
			}
        }

        protected function renderButtons()
        {
            echo CHtml::openTag('div', array('class' => 'buttons'));
            foreach ($this->buttons as $label => $htmlOptions)
            {
                $this->renderButton($label, $htmlOptions);
            }
            echo CHtml::closeTag('div');
        }

        protected function renderSetting($name, $metaData, $form = null, $return = false)
        {
            $defaults = array(
                'class' => array(),
                'type' => 'string',
                'labelOptions' => array(
                    'class' => 'control-label'
                )
            );
            $metaData = array_merge($defaults, $metaData);

            if (isset($this->prefix))
            {
                $name = "{$this->prefix}[$name]";
            }
            
            if (is_string($metaData['class']))
            {
                $metaData['class'] = array($metaData['class']);
            }
            if (isset($metaData['type']))
            {
                $function = "render{$metaData['type']}";

                // Handle localization.
                if (isset($metaData['localized']) && $metaData['localized'] == true)
                {
                    $name = "{$name}[{$metaData['language']}]";
                    if (isset($metaData['current']) && is_array($metaData['current']) && isset($metaData['current'][$metaData['language']]))
                    {
                        $metaData['current'] = $metaData['current'][$metaData['language']];
                    }
                    else
                    {
                        unset($metaData['current']);
                    }
                }

                // Handle styles
                if (isset($metaData['style']) && is_array($metaData['style']))
                {
                    $style = '';
                    foreach($metaData['style'] as $key => $value)
                    {
                        $style .= "$key : $value;";
                    }
                    $metaData['style'] = $style;
                }
                else
                {
                    $metaData['style'] = null;
                }

                $result = CHtml::tag('div',array('class'=>'setting', 'data-name' => $name), $this->$function($name, $metaData, $form));     // render inside a div
                
                if ($return)
                {
                    return $result;
                }
                else
                {
                    echo $result;
                }
            }
        }

        protected function renderSettings()
        {
            foreach($this->settings as $name => $metaData)
            {
                $this->renderSetting($name, $metaData);
            }
        }



        public function run() {
            parent::run();
            
            // Render settings
            $this->renderSettings();
            // Render buttons
            $this->renderButtons();
            // End form
            $this->endForm();
        }



        
        /***********************************************************************
         * Settings renderers.
         **********************************************************************/



        public function renderBoolean($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : (isset($metaData['default']) ? $metaData['default'] : NULL); // default value
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id);
            }
            $out .= CHtml::openTag('div', array('class' => 'boolean'));
            $out .= CHtml::radioButtonList($id, $value, array(
                0 => 'False',
                1 => 'True'
            ), array('id' => $id, 'form' => $form, 'container'=> false, 'separator' => ''));
            $out .= CHtml::closeTag('div');
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';

            return $out;
        }
        
        public function renderCheckbox($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? (bool) $metaData['current'] : FALSE;
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id);
            }
            $out .= CHtml::checkBox($id, $value, array('id' => $id, 'form' => $form, 'container'=>'div', 'separator' => ''));
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderFloat($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : '';
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $out .= CHtml::textField($id, $value, array(
                'id' => $id,
                'form' => $form,
                'pattern' => '\d+(\.\d+)?'
            ));
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderHtml($name, array $metaData, $form = null)
        {
           $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : (isset($metaData['default']) ? $metaData['default'] : NULL); // default value
            $metaData['class'][] = 'htmleditor';
            $readOnly = isset($metaData['readOnly']) ? $metaData['readOnly'] : false;
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $out .= Chtml::tag('div', array('class' => implode(' ', $metaData['class'])),
				$this->widget('bootstrap.widgets.TbHtml5Editor', array(
					'name' => $id,
                    'value' => $value,
					'width' => '100%',
					'editorOptions' => array(
						'html' => true,

					)
				), true)
			);
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderInt($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : (isset($metaData['default']) ? $metaData['default'] : NULL); // default value
            if (is_array($value)) { throw new CException('wrong type' . $name); }
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $out .= CHtml::textField($id, $value, array(
                'id' => $id,
                'form' => $form,
                'data-type' => 'int',
                'pattern' => '\d+'
            ));
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderJson($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : '';
            $readOnly = isset($metaData['readOnly']) ? $metaData['readOnly'] : false;
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $editorOptions = array_merge(array(
                'mode' => 'form',
                'modes' => array('form', 'code', 'tree', 'text')
            ), isset($metaData['editorOptions']) ? $metaData['editorOptions'] : array());
            $out .= $this->widget('ext.yii-jsoneditor.JsonEditor', array(
                'name' => $id,
                'value' => $value,
                'editorOptions' => $editorOptions
            ), true);
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
			      return $out;
        }

        public function renderLogo($name, array $metaData)
        {
            return CHtml::image($metaData['path']);
        }
        public function renderRelevance($name, array $metaData, $form = null)
        {
            $out = '';
            $metaData['class'][] = 'relevance';
            $id = $name;


            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $value = isset($metaData['current']) ? $metaData['current'] : '';

            $out .= CHtml::textArea($name, $value, array('id' => $id, 'form' => $form, 'class' => implode(' ', $metaData['class'])));
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderSelect($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : (isset($metaData['default']) ? $metaData['default'] : NULL);
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id);
            }
            $properties = array(
                'data' => $metaData['options'],
                'name' => $name,
                'value' => $value,
                'options' => array(
                    'minimumResultsForSearch' => 1000
                )
            );
            
            // allow to submit the form when this element changes
            if (isset($metaData['submitonchange']) && $metaData['submitonchange']) {
                $properties['events'] = array(
                    'change' => 'js: function(e) {
        this.form.submit();
}'
                );
            }
            
            $out .= App()->getController()->widget('ext.bootstrap.widgets.TbSelect2', $properties, true);
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderString($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : (isset($metaData['default']) ? $metaData['default'] : NULL); // default value
            $readOnly = isset($metaData['readOnly']) ? $metaData['readOnly'] : false;
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $out .= CHtml::textField($id, $value, array('id' => $id, 'form' => $form, 'class' => implode(' ', $metaData['class']), 'readonly' => $readOnly));
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderText($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : (isset($metaData['default']) ? $metaData['default'] : NULL); // default value
            $readOnly = isset($metaData['readOnly']) ? $metaData['readOnly'] : false;
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id);
            }
            $out .= CHtml::textArea($id, $value, array('id' => $id, 'form' => $form, 'readonly' => $readOnly, 'style' => $metaData['style']));
            // add a description to form fields - html-tag are possible
            (isset ($metaData['description'])) ? $out .= CHtml::tag ('div', array ('class' => 'description help-block'), $metaData['description'], TRUE) : '';
            return $out;
        }

        public function renderPassword($name, array $metaData, $form = null)
        {
            $out = '';
            $id = $name;
            $value = isset($metaData['current']) ? $metaData['current'] : '';
            if (isset($metaData['label']))
            {
                $out .= CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            $out .= CHtml::passwordField($id, $value, array('id' => $id, 'form' => $form));

            return $out;
        }

        public function renderList($name, array $metaData, $form = null)
        {
            $id = $name;
            if (isset($metaData['label']))
            {
                $result = CHtml::label($metaData['label'], $id, $metaData['labelOptions']);
            }
            else
            {
                $result = '';
            }

            $headers = '';
            $cells = '';
            foreach ($metaData['items'] as $itemName => $itemMetaData)
            {
                $headers .= CHtml::tag('th', array(), $itemMetaData['label']);
                unset($itemMetaData['label']);
                $cells .= CHtml::tag('td', array(), $this->renderSetting($itemName . '[]', $itemMetaData, $form, true));
            }
            $headers .= CHtml::tag('th');
            $cells .= CHtml::tag('td', array(), $this->widget('bootstrap.widgets.TbButtonGroup', array(
                'type' => 'link',
                'buttons' => array(
                    array('icon' => 'icon-minus', 'htmlOptions' => array('class' => 'remove')),
                    array('icon' => 'icon-plus', 'htmlOptions' => array('class' => 'add')),
                )
                
            ), true));
            $result .= CHtml::openTag('div', array('class' => 'settingslist'));
            $result .= CHtml::openTag('table');
            // Create header row.
            $result .= CHtml::openTag('thead');
            $result .= $headers;
            $result .= CHtml::closeTag('thead');
            // Create cells.
            $result .= CHtml::openTag('tbody');
            $result .= CHtml::openTag('tr');
            $result .= $cells;
            $result .= CHtml::closeTag('tr');
            $result .= CHtml::closeTag('tbody');
            $result .= CHtml::closeTag('table');
            $result .= CHtml::closeTag('div');
            return $result;
        }
    }

?>