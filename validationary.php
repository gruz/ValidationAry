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

		// ~ static public $debug = false;

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

				foreach (array('fields_selector_required', 'fields_selector_only_validate') as $k => $fields_selector)
				{
					$rule->$fields_selector = array_map('trim', explode(PHP_EOL, $rule->$fields_selector));
					$rule->$fields_selector = implode(', ', $rule->$fields_selector);

					$formsJQueryOptions[$rule->form_selector][$fields_selector] = $rule->$fields_selector;
				}

				$formsJQueryOptions[$rule->form_selector]['rule_number'] = $k;

				$formsJQueryOptions[$rule->form_selector]['submit_button_enabled'] = (bool) $rule->submit_button_enabled;
				$formsJQueryOptions[$rule->form_selector]['useAlerts'] = (bool) $rule->useAlerts;

				if (!$formsJQueryOptions[$rule->form_selector]['submit_button_enabled'] && $rule->fontawesome != 'none')
				{
					$rule->fontawesome = $rule->fontawesome;
				}
				else
				{
					$rule->fontawesome = false;
				}

				$formsJQueryOptions[$rule->form_selector]['fontawesome'] = $rule->fontawesome;
			}

			if (empty($formsJQueryOptions))
			{
				return;
			}

			// We add ajax here because we don't need it if there is no form loaded
			JHtml::_('jquery.framework');

			$doc = JFactory::getDocument();
			$doc->addScriptOptions($this->plg_name, array('forms' => $formsJQueryOptions ));

												// It's a must part
			$url_ajax_plugin = JURI::base() . '?option=com_ajax&format=raw&'

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
					. '&uniq=' . uniqid();

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

			$doc->addScriptOptions(
				$this->plg_name,
				array(
					'loading_snippet_light' => "<i aria-hidden='true' class='fa fa-spinner'></i> ")
			);

			$path_to_assets = '/plugins/' . $this->plg_type . '/' . $this->plg_name . '/';

			// ?h='.md5(dirname(__FILE__).'/js/ajax.js') makes sure that the JS is reloaded. After a plugin update Joomla may use browser cached JS or CSS.
			// ~ $doc->addScript($path_to_assets . '/js/ajax.js?h=' . md5_file(dirname(__FILE__) . '/js/ajax.js'));

			// ~ $this->_addJSorCSS($path_to_assets . 'js/ajax.js');
			self::addJSorCSS('ajax.js', $this->plg_full_name);

			// ~ $this->_addJSorCSS($path_to_assets . 'css/validationary.css');
			self::addJSorCSS('validationary.css', $this->plg_full_name);

			if ($this->params->get('fontawesome', 'included') == 'include')
			{
				$doc->addScript('https://use.fontawesome.com/51275d8707.js');
			}
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

			// ~ $group = $jinput->post->getCmd('group', null);

			$data = $jinput->post->get('jform', array(), 'array');

			$field_to_validate = $post->get('field_to_validate', null, 'raw');
			$field_value = $jinput->post->get($field_to_validate);

			// Normalize fields like jform[group][subgroup][subsubgroup][fieldname]
			// to group.subgroup.subsubgroup.fieldname
			$field_to_validate = str_replace('jform[', '', $field_to_validate);
			$field_to_validate = str_replace('][', '.', $field_to_validate);
			$field_to_validate = str_replace(']', '', $field_to_validate);
			$field_to_validate = explode('.', $field_to_validate);

			$field_value = $data;

			foreach ($field_to_validate as $k => $v)
			{
				$field_value = $field_value[$v];
			}

			// Email fields validation requires to know user id, which is stored in `id` field.
			// This works by default, but doesn't work for repeatable fields which we fix here

			$field_to_validate_id = $field_to_validate;
			$field_name_to_be_validated_tmp = array_pop($field_to_validate_id);

			if ($field_name_to_be_validated_tmp != 'id')
			{
				$field_to_validate_id[] = 'id';

				$field_value_id = $data;

				foreach ($field_to_validate_id as $k => $v)
				{
					if (isset($field_value_id[$v]))
					{
						$field_value_id = $field_value_id[$v];
					}
				}

				unset($field_to_validate_id);
			}

			// Get from group.subgroup.subsubgroup.fieldname
			// $group = group.subgroup.subsubgroup and $fieldNameToValidate = $field
			$fieldNameToValidate = array_pop($field_to_validate);

			// For repeatbale fields the field path should be trimmed
			$count = count($field_to_validate);

			// Clean up fields path from repeatable elements,
			// E.g. profile.child.child0 should only leave profile.child
			if ($count > 1)
			{
				foreach ($field_to_validate as $k => $pathElement)
				{
					if ($k === 0)
					{
						$prev = $field_to_validate[$k];
						continue;
					}

					$curr = $field_to_validate[$k];

					$suffix = str_replace($prev, '', $curr);

					// If is integer
					if (ctype_digit(strval($suffix)))
					{
						unset($field_to_validate[$k]);
						continue;
					}

					$prev = $field_to_validate[$k];
				}
			}

			$group = implode('.', $field_to_validate);

			if (!empty($group))
			{
				$this->_setArrayValueByPath($data, $group . '.' . $fieldNameToValidate, $field_value);

				// Set additional id variable if case of nested fields
				// Specially for nested repeatable email check
				if ($field_name_to_be_validated_tmp != 'id')
				{
					$this->_setArrayValueByPath($data, $group . '.' . 'id', $field_value_id);
				}
			}

			$return = array(
				'message' => '',
				'type' => '',
				'continue' => true
			);

			$rule_number = $jinput->post->get('rule_number', null, 'int');
			$settingsName = 'form-settings-group';
			$rules = $this->params->get($settingsName, array());
			$rule = $rules->{$settingsName . $rule_number};

			$xmls_to_load = $rule->xml_path;

			if (empty($xmls_to_load))
			{
				$form_option = $jinput->post->getCmd('form_option', null);
				$form_task = $jinput->post->getCmd('form_task', null);

				if (!empty($form_option) && !empty($form_task))
				{
					$xmls_to_load = explode('.', $form_task);
					$xmls_to_load[] = JPATH_ROOT . '/components/' . $form_option . '/models/forms/' . $xmls_to_load[0] . '.xml';
				}
			}
			else
			{
				$xmls_to_load_temp = explode(PHP_EOL, $xmls_to_load);
				$xmls_to_load = array();

				foreach ($xmls_to_load_temp as $k => $fileOrPattern)
				{
					$fileOrPattern = trim(JPATH_ROOT . '/' . $fileOrPattern);

					if (JFile::exists($fileOrPattern))
					{
						$xmls_to_load[] = $fileOrPattern;
					}
					else
					{
						foreach (glob($fileOrPattern) as $filename)
						{
							if (JFile::exists($filename))
							{
								$xmls_to_load[] = $filename;
							}
						}
					}
				}
			}

			if (empty($xmls_to_load))
			{
				$return['message'] = JText::_('PLG_SYSTEM_VALIDATIONARY_COULD_NOT_FIND_FORM_XML_TO_VALIDATE');
				self::_JResponseJson($return, $return['message'], $taksFailed = true);
			}

			// Need to use this stupid construction, as JForm must be called with a parameter'
			$form = new JForm($xmls_to_load[0]);

			// $form->addFormPath();
			foreach ($xmls_to_load as $k => $xml_to_load)
			{
				$form->loadFile($xml_to_load);
			}

			$xml = $form->getXml();

			if (!$form)
			{
				$return['message'] = $form->getErrors();
				$return['type'] = 'error';
				$return['continue'] = false;
			}
			else
			{
				if (!empty($rule->subform_selectors))
				{
					$subform_selectors = array_map('trim', explode(PHP_EOL, $rule->subform_selectors));

					$thereAreSubforms = true;

					while ($thereAreSubforms)
					{
						$thereAreSubforms = false;

						$subforms = array();

						foreach ($subform_selectors as $subform_selector)
						{
							// This works with PHP 5, but not with PHP 7. Max Manets fixed.
							// ~ $xpath    = '//field[@type="' . $subform_selector . '"]';
							$xpath    = '//field[type="' . $subform_selector . '"]';
							$subforms = array_merge($subforms, $form->getXML()->xpath($xpath));
						}

						if (!empty($subforms))
						{
							$thereAreSubforms = true;
						}

						foreach ($subforms as $i => $subFormField)
						{
							$subformPath      = JPATH_ROOT . '/' . $subFormField['formsource'];
							$subfromAsElement = new SimpleXMLElement($subformPath, null, true);
							$wrapperElement   = new SimpleXMLElement('<fields name="' . $subFormField['name'] . '" />');
							$this->_addNode($wrapperElement, $subfromAsElement);
							$parentElement = $subFormField->xpath("..")[0];
							$this->_addNode($parentElement, $wrapperElement);
							unset($subFormField->{0});
							continue;
						}
					}
				}

				$groupName = $group;

				if (empty($group))
				{
					$groupName = 'nogroup';
				}

				$fieldsToPreserve[$groupName][] = $fieldNameToValidate;

				$field_to_validate = $form->getField($fieldNameToValidate, $group);

// ~ echo $form->getXML()->asXML();
// ~ exit;

				// To properly validate editing unique fields like email or username
				// on editing existing records, we tell not to remove the id field used by email and username rules
				$fieldsToPreserve['nogroup'][] = 'id';
				$fieldsToPreserve[$groupName][] = 'id';

				/* Do not remove, maybe will need to use.
				$fieldUnique = $field_to_validate->getAttribute('unique');
				$fieldType = $field_to_validate->getAttribute('type');
				if ($fieldUnique)
				{
				}
				*/

				if (!$field_to_validate)
				{
					// Load sys language file
					$this->default_lang = JComponentHelper::getParams('com_languages')->get('site');
					$language = JFactory::getLanguage();
					$this->plg_path = JPATH_PLUGINS . '/' . $this->plg_type . '/' . $this->plg_name . '/';

					$language->load($this->plg_full_name . '.sys', $this->plg_path, 'en-GB', true);
					$language->load($this->plg_full_name . '.sys', $this->plg_path, $this->default_lang, true);

					// Message header
					$return['message'] = JText::_('PLG_SYSTEM_VALIDATIONARY');

					// JResponse handles Joomla messages. So we use it.
					JFactory::getApplication()->enqueueMessage(JText::_('PLG_SYSTEM_VALIDATIONARY_FIELD_NOT_FOUND'), 'warning');
					$return['type'] = 'warning';
					$return['continue'] = false;
					self::_JResponseJson($return, $return['message'], $taksFailed = !$return['continue']);
				}

				$validateRule = $field_to_validate->getAttribute('validate');

				if ($validateRule == 'equals')
				{
					$tmp_name = $field_to_validate->getAttribute('field');
					$tmp_name = str_replace($groupName . '.', '', $tmp_name);
					$fieldsToPreserve[$groupName][] = $tmp_name;

					if (!empty($group))
					{
						$equal_field_value = $data;
						$equal_field_path = explode('.', $groupName);
						$equal_field_path[] = $last;
						$equal_field_path[] = $tmp_name;

						foreach ($equal_field_path as $k => $v)
						{
							$equal_field_value = $equal_field_value[$v];
						}

						$this->_setArrayValueByPath($data, $group . '.' . $tmp_name, $equal_field_value);

						// ~ $data[$groupName . '.' . $tmp_name] = $equal_field_value;
						$fieldsToPreserve[$groupName][] = $tmp_name;

						// ~ $equalFieldElement = new SimpleXMLElement('<field name="' . $group . '.' . $tmp_name . '" />');
						// ~ $this->_addNode($form->getXML(), $equalFieldElement);
					}
				}

				// Find if there is another element which must be equal to the current one
				// If there is such an element, then notify JS to recheck it (pass )

				if (!empty($group))
				{
					$xpath = $group;
					$xpath = explode('.', $xpath);

					for ($i = 0; $i < count($xpath); $i++)
					{
						$xpath[$i] = '//fields[@name="' . $xpath[$i] . '"]';
					}

					$xpath[] = '//field[@validate="equals"][@field="' . $fieldNameToValidate . '"]';

					$xpath = implode('', $xpath);
				}
				else
				{
					$xpath = '//field[@validate="equals"][@field="' . $fieldNameToValidate . '"]';
				}

				$elementsToRecheck = $form->getXML()->xpath($xpath);

				foreach ($elementsToRecheck as $elementToRechek)
				{
					$fieldToRecheckName = $elementToRechek['name'];
					$path = "";

					while (true)
					{
							// Determine preceding and following elements, build a position predicate from it.
							$elementName = $elementToRechek->getName();
							$preceding = $elementToRechek->xpath("preceding-sibling::" . $elementName);
							$following = $elementToRechek->xpath("following-sibling::" . $elementName);

							// ~ $predicate = (count($preceding) + count($following)) > 0 ? "[".(count($preceding)+1)."]" : "";
							if ($elementName == 'fields')
							{
								// ~ $path = "/".$elementToRechek['name'].$predicate.$path;
								$path = $elementToRechek['name'] . "." . $path;
							}
							// Is there a parent node? Then go on.
							$elementToRechek = $elementToRechek->xpath("parent::*");

							if (count($elementToRechek) > 0)
							{
								$elementToRechek = $elementToRechek[0];
							}
							else
							{
								break;
							}
					}

					if (!empty($last))
					{
						$path = $path . $last . '.' . $fieldToRecheckName;
					}
					else
					{
						$path = $path . $fieldToRecheckName;
					}

					$path = str_replace('.', '][', $path);

					// ~ $path = $this->_getElementPath($elementToRechek);
					$return['reCheckFields'][] = 'jform[' . $path . ']';
				}

				// To validate one field, we need to remove all other fields except some vital ones like id, stored id $fieldsToPreserve
				// We cannot reset XML and read our new one, as we want to preserve the needed fields attributes
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

						$fieldName = $field->getAttribute('name');

						$fieldGroupName = 'nogroup';

						$tmp = (string) $field->group;

						if (!empty($tmp))
						{
							$fieldGroupName = $field->group;
						}

						if (!isset($fieldsToPreserve[$fieldGroupName]) || !in_array($fieldName, $fieldsToPreserve[$fieldGroupName]))
						{
							$form->removeField($fieldName, $field->group);
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

				// Get our fake model to be able to reuse JModelFrom::validate
				JModelLegacy::addIncludePath(dirname(__FILE__) . '/model/');
				$model = JModelLegacy::getInstance('Validate', 'ValidationAryModel');

				$form->bind($data);

// ~ echo $form->getXML()->asXML();
// ~ exit;

				$validData = $model->validate($form, $data, $group);

				// Check for validation errors.
				if ($validData === false)
				{
						$return['message'] = '';
						$return['type'] = 'warning';
						$return['continue'] = false;

					// Get the validation messages.
					$errors = $model->getErrors();

					if (count($errors) > 0)
					{
						$this->tryLoadLanguageForXMLForm($form);
					}

					foreach ($errors as $error)
					{
						foreach ($error->getTrace() as $ke => $errorFieldXMLObj)
						{
							if ($errorFieldXMLObj['class'] == 'JForm')
							{
								$errorFieldXMLObj = $errorFieldXMLObj['args'][0];
								$name = (string) $errorFieldXMLObj['name'];
								break;
							}
						}

						// Get error only for the needed field
						if ($name != $fieldNameToValidate)
						{
							continue;
						}

						if ($error instanceof Exception)
						{
							$tmp = (string) $errorFieldXMLObj['message'];

							if ($errorFieldXMLObj && !empty($tmp))
							{
								$return['message'] = JText::_((string) $errorFieldXMLObj['message']);
							}
							else
							{
								$return['message'] = $error->getMessage();
							}

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

		/**
		 * Adds a new child SimpleXMLElement node to the source.
		 * Copied from JForm class, where it's alas protected and cannot be reused
		 *
		 * @param   SimpleXMLElement  $source  The source element on which to append.
		 * @param   SimpleXMLElement  $new     The new element to append.
		 *
		 * @return  void
		 *
		 * @since   11.1
		 */
		protected static function _addNode(SimpleXMLElement $source, SimpleXMLElement $new)
		{
			// Add the new child node.
			$node = $source->addChild($new->getName(), htmlspecialchars(trim($new)));

			// Add the attributes of the child node.
			foreach ($new->attributes() as $name => $value)
			{
				$node->addAttribute($name, $value);
			}

			// Add any children of the new node.
			foreach ($new->children() as $child)
			{
				self::_addNode($node, $child);
			}
		}

		/**
		 * Sets a value to an array by a string path
		 *
		 * Taken from http://stackoverflow.com/questions/9628176/using-a-string-path-to-set-nested-array-data
		 *
		 * @param   array   &$data  Objects are converted into arrays
		 * @param   string  $path   Dot separated path
		 * @param   mixed   $value  Value to be set
		 *
		 * @return   type  Description
		 */
		public static function _setArrayValueByPath(&$data, $path, $value)
		{
			if (!is_array($path))
			{
				$path = explode('.', $path);
			}

			// This code is
			$temp = &$data;

			foreach ($path as $key)
			{
				if (is_object($temp))
				{
					$temp = &$temp->$key;
				}
				else
				{
					$temp = &$temp[$key];
				}
			}

			$temp = $value;
			unset($temp);
		}

		/**
		 * Used to modyfy HEAD section
		 *
		 * Removes core Joomla validation if needed
		 *
		 * @return   void
		 */
		public function onBeforeCompileHead()
		{
			$joomla_validate_remove = $this->params->get('joomla_validate_remove', false);

			if (!$joomla_validate_remove)
			{
				return;
			}

			$doc = JFactory::getDocument();

		// Remove core old Bootstrap 2
			unset($doc->_scripts[JURI::base(true) . '/media/system/js/validate.js']);
		}
	}
}
