<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package Core
 * @link    http://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;


/**
 * Class OptionWizard
 *
 * Provide methods to handle form field options.
 * @copyright  Leo Feyer 2005-2012
 * @author     Leo Feyer <http://contao.org>
 * @package    Core
 */
class OptionWizard extends \Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';


	/**
	 * Validate the input and set the value
	 */
	public function validate()
	{
		$mandatory = $this->mandatory;
		$options = deserialize($this->getPost($this->strName));

		// Check labels only (values can be empty)
		if (is_array($options))
		{
			foreach ($options as $key=>$option)
			{
				// Unset empty rows
				if ($option['label'] == '')
				{
					unset($options[$key]);
					continue;
				}

				$options[$key]['label'] = trim($option['label']);
				$options[$key]['value'] = trim($option['value']);

				if ($options[$key]['label'] != '')
				{
					$this->mandatory = false;
				}
			}
		}

		$options = array_values($options);
		$varInput = $this->validator($options);

		if (!$this->hasErrors())
		{
			$this->varValue = $varInput;
		}

		// Reset the property
		if ($mandatory)
		{
			$this->mandatory = true;
		}
	}


	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		$arrButtons = array('copy', 'up', 'down', 'delete');
		$strCommand = 'cmd_' . $this->strField;

		// Change the order
		if (\Input::get($strCommand) && is_numeric(\Input::get('cid')) && \Input::get('id') == $this->currentRecord)
		{
			$this->import('Database');

			switch (\Input::get($strCommand))
			{
				case 'copy':
					array_insert($this->varValue, \Input::get('cid'), array($this->varValue[\Input::get('cid')]));
					break;

				case 'up':
					$this->varValue = array_move_up($this->varValue, \Input::get('cid'));
					break;

				case 'down':
					$this->varValue = array_move_down($this->varValue, \Input::get('cid'));
					break;

				case 'delete':
					$this->varValue = array_delete($this->varValue, \Input::get('cid'));
					break;
			}

			$this->Database->prepare("UPDATE " . $this->strTable . " SET " . $this->strField . "=? WHERE id=?")
						   ->execute(serialize($this->varValue), $this->currentRecord);

			$this->redirect(preg_replace('/&(amp;)?cid=[^&]*/i', '', preg_replace('/&(amp;)?' . preg_quote($strCommand, '/') . '=[^&]*/i', '', \Environment::get('request'))));
		}

		// Make sure there is at least an empty array
		if (!is_array($this->varValue) || !$this->varValue[0])
		{
			$this->varValue = array(array(''));
		}

		// Begin the table
		$return = '<table class="tl_optionwizard" id="ctrl_'.$this->strId.'">
  <thead>
    <tr>
      <th>'.$GLOBALS['TL_LANG']['MSC']['ow_value'].'</th>
      <th>'.$GLOBALS['TL_LANG']['MSC']['ow_label'].'</th>
      <th>&nbsp;</th>
      <th>&nbsp;</th>
      <th>&nbsp;</th>
    </tr>
  </thead>
  <tbody>';

		$tabindex = 0;

		// Add fields
		for ($i=0; $i<count($this->varValue); $i++)
		{
			$return .= '
    <tr>
      <td><input type="text" name="'.$this->strId.'['.$i.'][value]" id="'.$this->strId.'_value_'.$i.'" class="tl_text_2" tabindex="'.++$tabindex.'" value="'.specialchars($this->varValue[$i]['value']).'"></td>
      <td><input type="text" name="'.$this->strId.'['.$i.'][label]" id="'.$this->strId.'_label_'.$i.'" class="tl_text_2" tabindex="'.++$tabindex.'" value="'.specialchars($this->varValue[$i]['label']).'"></td>
      <td><input type="checkbox" name="'.$this->strId.'['.$i.'][default]" id="'.$this->strId.'_default_'.$i.'" class="fw_checkbox" tabindex="'.++$tabindex.'" value="1"'.($this->varValue[$i]['default'] ? ' checked="checked"' : '').'> <label for="'.$this->strId.'_default_'.$i.'">'.$GLOBALS['TL_LANG']['MSC']['ow_default'].'</label></td>
      <td><input type="checkbox" name="'.$this->strId.'['.$i.'][group]" id="'.$this->strId.'_group_'.$i.'" class="fw_checkbox" tabindex="'.++$tabindex.'" value="1"'.($this->varValue[$i]['group'] ? ' checked="checked"' : '').'> <label for="'.$this->strId.'_group_'.$i.'">'.$GLOBALS['TL_LANG']['MSC']['ow_group'].'</label></td>';

			// Add row buttons
			$return .= '
      <td style="white-space:nowrap; padding-left:3px">';

			foreach ($arrButtons as $button)
			{
				$return .= '<a href="'.$this->addToUrl('&amp;'.$strCommand.'='.$button.'&amp;cid='.$i.'&amp;id='.$this->currentRecord).'" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['ow_'.$button]).'" onclick="Backend.optionsWizard(this,\''.$button.'\',\'ctrl_'.$this->strId.'\');return false">'.\Image::getHtml($button.'.gif', $GLOBALS['TL_LANG']['MSC']['ow_'.$button]).'</a> ';
			}

			$return .= '</td>
    </tr>';
		}

		return $return.'
  </tbody>
  </table>';
	}
}