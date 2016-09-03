<?php
/**
 * ValidationAry plugin
 *
 * @package    ValidationAry
 *
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  Copyleft (є) 2016 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

jimport('gjfields.helper.plugin');

$latest_gjfields_needed_version = '1.1.18';

$isOk = true;

while (true)
{
	$isOk = false;

	$xml = JPATH_ROOT . '/libraries/gjfields/gjfields.xml';

	if (!file_exists($xml))
	{
		$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'
		. __FILE__ . '</span><br> The library should be installed together with the extension... Anyway, reinstall it:'
		. ' <a href="http://gruz.org.ua/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}

	$gjfields_version = file_get_contents($xml);
	preg_match('~<version>(.*)</version>~Ui', $gjfields_version, $gjfields_version);
	$gjfields_version = $gjfields_version[1];

	if (version_compare($gjfields_version, $latest_gjfields_needed_version, '<'))
	{
		$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'
			. __FILE__ . '</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}

	$isOk = true;
	break;
}

if (!$isOk)
{
	JFactory::getApplication()->enqueueMessage($error_msg, 'error');
}
else
{
	/**
	 * Validationary
	 *
	 * @author  Gruz <arygroup@gmail.com>
	 * @since   0.0.1
	 */
	class PlgSystemValidationary extends JPluginGJFields
				{
		static public $debug = false;

		/**
		 * The entry point of the plugin
		 *
		 * @return   void
		 */
		public function onAfterInitialise()
		{
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			// Remove repeat subform margin left if label is empty
			// Otherwise the repeatform doesn't fill all the page width
			if ($this->checkIfNowIsCurrentPluginEditWindow())
			{
				$doc = JFactory::getDocument();
				$js = '
					jQuery(document).ready(function($){
						var label = $(".subform-repeatable-wrapper").closest("div.control-group").find(".control-label:first").text().trim();
						if (label.length === 0)
						{
							$(".subform-repeatable-wrapper").closest("div.controls").css("margin-left", "0");
						}

					});
				';
				$doc->addScriptDeclaration($js);
			}

			$rules = $this->params->get('form-settings-group', array());

			if (empty($rules))
			{
				return;
			}

			$formsJQueryOptions = array();

			foreach ($rules as $k => $rule)
			{
				if (JFactory::getApplication()->isAdmin() && $rule->scope == 'site')
				{
					continue;
				}

				if (!JFactory::getApplication()->isAdmin() && $rule->scope == 'admin')
				{
					continue;
				}

				$rule->form_selector = array_map('trim', explode(PHP_EOL, $rule->form_selector));
				$rule->form_selector = implode(', ', $rule->form_selector);

				$rule->fields_selector = array_map('trim', explode(PHP_EOL, $rule->fields_selector));
				$rule->fields_selector = implode(', ', $rule->fields_selector);

				$formsJQueryOptions[$rule->form_selector]['fields_selector'] = $rule->fields_selector;

				if (empty($rule->xml_path))
				{
					$rule->xml_path = '';
				}

				$formsJQueryOptions[$rule->form_selector]['xml_path'] = $rule->xml_path;

				$formsJQueryOptions[$rule->form_selector]['submit_button_enabled'] = (bool) $rule->submit_button_enabled;
			}

			if (empty($formsJQueryOptions))
			{
				return;
			}

			// We add ajax here because we don't need it if there is no form loaded
			JHtml::_('jquery.framework');

			$doc = JFactory::getDocument();
			$doc->addScriptOptions($this->plg_name, array('forms' => $formsJQueryOptions ));

			$url_ajax_plugin = JRoute::_(
				// It's a must
					JURI::base() . '?option=com_ajax&format=raw&'

					// Pass token. Since ValidationAry posts the form itself, it has no sense.
					// But what if we want to validate a single field?
					. JSession::getFormToken() . '=1'

					// $this->plg_type should contain your plugin group (system, content etc.),
					// E.g. for a system plugin plg_system_menuary it should be system
					. '&group=' . $this->plg_type

					// The function from plugin you want to call

					// The PHP functon must start from onAjax e.g. PlgSystemValidationAry::onAjaxValidate,
					// while here we should use only after onAjax - `validate`
					. '&plugin=validate'

					// It's optional to add to the link. Just in case to ignore link result caching.
					. '&uniq=' . uniqid()
				);

			// Below some optional stuff

			// Since you want to use AJAX, you need a DOM element to place the response
			// Here is an example how to place an ajax placeholder as a Joomla message

			// $ajax_place = '<div id="my_ajax_place"></div>';
			// JFactory::getApplication()->enqueueMessage($ajax_place, 'notice');

			// Add the link to the HTML DOM to let later your ajax JS script get the link to call
			// You'll be able to get the link in JS like <code>var link = Joomla.optionsStorage.ary.ajax_url;</code>

			$doc->addScriptOptions($this->plg_name, array('ajax_url' => $url_ajax_plugin ));

			$doc->addScriptOptions($this->plg_name, array('behavior' => $this->params->get('behavior', 'bootstrap2') ));

			$doc->addScriptOptions(
				$this->plg_name,
				array(
					'loading_snippet' => "<div class='center-block text-center'>"
					. "<i aria-hidden='true' class='center-block text-center fa fa-spinner fa-spin '></i>"
					. "</div>")
			);

			$path_to_assets = '/plugins/' . $this->plg_type . '/' . $this->plg_name . '/';

			// ?h='.md5(dirname(__FILE__).'/js/ajax.js') makes sure that the JS is reloaded. After a plugin update Joomla may use browser cached JS or CSS.
			// ~ $doc->addScript($path_to_assets . '/js/ajax.js?h=' . md5_file(dirname(__FILE__) . '/js/ajax.js'));
			$this->_addJSorCSS($path_to_assets . '/js/ajax.js');
			$this->_addJSorCSS($path_to_assets . '/css/validationary.css');
		}

		/**
		 * Entry point for Ajax data passed via AJAX plugin
		 *
		 * @return   void
		 */
		public function onAjaxValidate ()
		{
			self::checkToken();

			$jinput  = JFactory::getApplication()->input;

			$post = JFactory::getApplication()->input->post;

			$group = $jinput->post->getCmd('group', '');

			// Get our fake model to be able to reuse JModelFrom::validate
			JModelLegacy::addIncludePath(dirname(__FILE__) . '/model/');
			$model = JModelLegacy::getInstance('Validate', 'ValidationAryModel');

			$data = $jinput->post->get('jform', array(), 'array');

			$field_to_validate = $post->get('field_to_validate', null, 'raw');

			if (!empty($group))
			{
				$pattern = '~jform\[.*\]\[(.*)\]~Ui';
			}
			else
			{
				$pattern = '~jform\[(.*)\]~Ui';
			}

			preg_match($pattern, $field_to_validate, $fieldNameToValidate);
			$fieldNameToValidate = end($fieldNameToValidate);

			if (empty($fieldNameToValidate))
			{
				$fieldNameToValidate = array($field_to_validate);
			}

			$return = array(
				'message' => '',
				'type' => '',
				'continue' => true
			);

			$xml_to_load = $jinput->post->get('xml_path', null, 'raw');

			if (empty($xml_to_load))
			{
				$form_option = $jinput->post->getCmd('form_option', null);
				$form_task = $jinput->post->getCmd('form_task', null);

				if (!empty($form_option) && !empty($form_task))
				{
					$xml_to_load = explode('.', $form_task);
					$xml_to_load = $xml_to_load[0];
					$xml_to_load = JPATH_ROOT . '/components/' . $form_option . '/models/forms/' . $xml_to_load . '.xml';
				}
			}

			if (!empty($xml_to_load))
			{
				if (!JFile::exists($xml_to_load))
				{
					$xml_to_load = JPATH_ROOT . '/' . $xml_to_load;

					if (!JFile::exists($xml_to_load))
					{
						$xml_to_load = null;
					}
				}
			}

			if (empty($xml_to_load))
			{
				$return['message'] = JText::_('PLG_SYSTEM_VALIDATIONARY_COULD_NOT_FIND_FORM_XML_TO_VALIDATE');
				self::_JResponseJson($return, $return['message'], $taksFailed = true);
			}

			// Need to use this stupid construction, as JForm must be called with a parameter'
			$form = new JForm($xml_to_load);
			$form->addFormPath();
			$form->loadFile($xml_to_load);

			if (!$form)
			{
				$return['message'] = $form->getErrors();
				$return['type'] = 'error';
				$return['continue'] = false;
			}
			else
			{
				$fieldsToPreserve = array($fieldNameToValidate);

				$field_to_validate = $form->getField($fieldNameToValidate);
				$validateRule = $field_to_validate->getAttribute('validate');

				if ($validateRule == 'equals')
				{
					$fieldsToPreserve[] = $field_to_validate->getAttribute('field');
				}

				// Find if there is another element which must be equal to the current one
				// If there is such an element, then notify JS to recheck it (pass )
				$xpath = '//field[@validate="equals"][@field="' . $field_to_validate->getAttribute('name') . '"]';

				$elementsToRecheck = $form->getXML()->xpath($xpath);

				foreach ($elementsToRecheck as $elementToRechek)
				{
					$return['reCheckFields'][] = 'jform[' . $elementToRechek['name'] . ']';
				}

				// To validate one filed, we need to remove all other fields
				foreach ($form->getFieldsets() as $fieldset)
				{
					$fields = $form->getFieldset($fieldset->name);

					// Validate the fields.
					foreach ($fields as $field)
					{
						if (empty($group))
						{
							$name = 'jform[' . $field->getAttribute('name') . ']';
						}
						else
						{
							$name = 'jform[' . $group . '][' . $field->getAttribute('name') . '][]';
						}

						$filedName = $field->getAttribute('name');

						if (!in_array($filedName, $fieldsToPreserve))
						{
							$form->removeField($filedName);
						}
						// Try loading language
						else
						{
							$extensions_to_load_languages = array();

							$attributes_to_check = array('label', 'description', 'message');

							foreach ($attributes_to_check as $attrName)
							{
								$attrValue = $field->getAttribute($attrName);

								if (!empty($attrValue))
								{
									$attrValue = explode('_', $attrValue);

									if (count($attrValue) > 2 )
									{
										$attrValue = strtolower($attrValue[0] . '_' . $attrValue[1]);
										$extensions_to_load_languages[$attrValue] = $attrValue;
									}
								}
							}

							foreach ($extensions_to_load_languages as $extension)
							{
								$default_lang = JComponentHelper::getParams('com_languages')->get('site');
								$language = JFactory::getLanguage();
								$language->load($extension, JPATH_ROOT, 'en-GB', true);
								$language->load($extension, JPATH_ROOT, $default_lang, true);
							}
						}
					}
				}

				// Test whether the data is valid.
				$validData = $model->validate($form, $data, $group);

				// Check for validation errors.
				if ($validData === false)
				{
					// Get the validation messages.
					$errors = $model->getErrors();

					if (count($errors) > 0)
					{
						$this->tryLoadLanguageForXMLForm($form);
					}

					foreach ($errors as $error)
					{
						$errorFieldXMLObj = $error->getTrace();
						$errorFieldXMLObj = $errorFieldXMLObj[0]['args'][0];
						$name = (string) $errorFieldXMLObj['name'];

						// Get error only for the needed field
						if ($name != $fieldNameToValidate)
						{
							continue;
						}

						if ($error instanceof Exception)
						{
							$return['message'] = $error->getMessage();
							$return['type'] = 'warning';
							$return['continue'] = false;
						}
						else
						{
							$return['message'] = $error;
							$return['type'] = 'warning';
							$return['continue'] = false;
						}
					}
				}
			}

			self::_JResponseJson($return, $return['message'], $taksFailed = !$return['continue']);
		}

		/**
		 * Tries to load language files
		 *
		 * Iterates the passed form and tries to determine extensions
		 * which language files have to be loaded
		 *
		 * @param   JForm  $form  JForm with loaded XML file
		 *
		 * @return   void
		 */
		public function tryLoadLanguageForXMLForm($form)
		{
			// To validate one filed, we need to remove all other fields
			foreach ($form->getFieldsets() as $fieldset)
			{
				$fields = $form->getFieldset($fieldset->name);

				// Validate the fields.
				foreach ($fields as $field)
				{
					$extensions_to_load_languages = array();

					$attributes_to_check = array('label', 'description', 'message');

					foreach ($attributes_to_check as $attrName)
					{
						$attrValue = $field->getAttribute($attrName);

						if (!empty($attrValue))
						{
							$attrValue = explode('_', $attrValue);

							if (count($attrValue) > 2 )
							{
								$attrValue = strtolower($attrValue[0] . '_' . $attrValue[1]);
								$extensions_to_load_languages[$attrValue] = $attrValue;
							}
						}
					}
				}
			}

			foreach ($extensions_to_load_languages as $extension)
			{
				$default_lang = JComponentHelper::getParams('com_languages')->get('site');
				$language = JFactory::getLanguage();
				$language->load($extension, JPATH_ROOT, 'en-GB', true);
				$language->load($extension, JPATH_ROOT, $default_lang, true);
			}
		}
	}
}
