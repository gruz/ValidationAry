<?php
/**
 * @package     Joomla.Site
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Fake model to validate a field
 *
 * @since  1.5
 */
class ValidationAryModelValidate extends JModelForm
{
	/**
	 * Method to get the contact form.
	 * The base form is loaded from XML and then an event is fired
	 *
	 * @param   array    $data      An optional array of data for the form to interrogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  JForm  A JForm object on success, false on failure
	 *
	 * @since   1.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		return false;
	}
}
