<?php
/**
 * The installer script which installs languages and performs migrating
 *
 * @package    ValidationAry
 *
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  Copyleft (є) 2016 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

if (!class_exists('ScriptAry'))
{
	include dirname(__FILE__) . '/scriptary.php';
}

if (!class_exists('ScriptAry'))
{
	include dirname(__FILE__) . '/scriptary.php';
}

/**
 * Installer script
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class PlgSystemValidationAryInstallerScript extends ScriptAry
{
	/**
	 * Method to uninstall the component
	 *
	 * @param   object  $parent  Is the class calling this method
	 *
	 * @return  void
	 */
	public function uninstall($parent)
	{
		// $parent

		$this->messages[] = '<p>'
			. JText::_('You may wish to uninstall GJFields library used together with this extension. Other extensions may also use GJFields.'
				. ' If you uninstall GJFields by mistake, you can always reinstall it.')
			. '</p>';

		parent::uninstall($parent);
	}

	/**
	 * Tell parent script not to publish current plugin
	 *
	 * @param   string  $type           Is the type of change (install, update or discover_install)
	 * @param   object  $parent         Is the class calling this method
	 * @param   bool    $publishPlugin  Used here not to violate strict standards
	 *
	 * @return   void
	 */
	public function postflight($type, $parent, $publishPlugin = true)
	{
		parent::postflight($type, $parent, $publishPlugin = false);
	}
}
