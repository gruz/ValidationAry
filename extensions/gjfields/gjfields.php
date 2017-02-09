<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

// No direct access
defined('_JEXEC') or die();

/**
 * Base class to extend with gjfields fileds
 *
 * @since  0.0.1
 */
class GJFieldsFormField extends JFormField
{
	/**
	 * Constructor
	 *
	 * Loads some
	 *
	 * @param   object  $form  XML form
	 */
	public function __construct($form = null)
	{
		parent::__construct($form);
		JHTML::_('behavior.framework', true);
		$app = JFactory::getApplication();

		if (!$app->get($this->type . '_initialized', false))
		{
			$app->set($this->type . '_initialized', true);

			$url_to_assets = JURI::root() . '/libraries/gjfields/';
			$path_to_assets = JPATH_ROOT . '/libraries/gjfields/';
			$doc = JFactory::getDocument();

			$cssname = $url_to_assets . 'css/common.css';
			$cssname_path = $path_to_assets . 'css/common.css';

			if (file_exists($cssname_path))
			{
				$doc->addStyleSheet($cssname);
			}

			$this->type = JString::strtolower($this->type);

			$cssname = $url_to_assets . 'css/' . $this->type . '.css';
			$cssname_path = $path_to_assets . 'css/' . $this->type . '.css';

			if (file_exists($cssname_path))
			{
				$doc->addStyleSheet($cssname);
			}

			$jversion = new JVersion;
			$common_script = $url_to_assets . 'js/script.js?v=' . $jversion->RELEASE;

			$doc->addScript($common_script);

			$scriptname = $url_to_assets . 'js/' . $this->type . '.js?v=' . $this->_getGJFieldsVersion();
			$scriptname_path = $path_to_assets . 'js/' . $this->type . '.js';

			if (file_exists($scriptname_path))
			{
				$doc->addScript($scriptname);
			}
		}

		$this->HTMLtype = 'div';

		if (JFactory::getApplication()->isAdmin() && JFactory::getApplication()->getTemplate() !== 'isis')
		{
			$this->HTMLtype = 'li';
		}

		$var_name = basename(__FILE__, '.php') . '_HTMLtype';

		if (!$app->get($var_name, false))
		{
			$app->set($var_name, true);
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration('var ' . $var_name . ' = "' . $this->HTMLtype . '";');
			$doc->addScriptDeclaration('var lang_reset = "' . JText::_('JSEARCH_RESET') . '?";');
		}
	}

	/**
	 * A cap
	 *
	 * @return   void
	 */
	public function getInput()
	{
	}

	/**
	 * Gets a default value for a field
	 *
	 * A very old NN legacy function
	 *
	 * @param   mixed  $val      Element name
	 * @param   mixed  $default  Default value
	 *
	 * @return   mixed  Either current or default value of an XML field
	 */
	public function def($val, $default = '')
	{
		return ( isset( $this->element[$val] ) && (string) $this->element[$val] != '' ) ? (string) $this->element[$val] : $default;
	}

	/**
	 * Gets GJFields version numbed from the library .xml file
	 *
	 * @return   string  Version number
	 */
	static public function _getGJFieldsVersion ()
	{
		$gjfields_version = file_get_contents(dirname(__FILE__) . '/gjfields.xml');
		preg_match('~<version>(.*)</version>~Ui', $gjfields_version, $gjfields_version);
		$gjfields_version = $gjfields_version[1];

		return $gjfields_version;
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldGJFields'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldGJFields extends GJFieldsFormField
				{
				}
}
