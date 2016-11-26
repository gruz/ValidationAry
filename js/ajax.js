/*
	(function($){
		$.event.special.destroyed = {
			remove: function(o) {
				if (o.handler) {
					o.handler()
				}
			}
		}
	})(jQuery)
*/
jQuery(document).ready(function($){

	var debug = true;
	var debug = false;
if (debug) console.log(Joomla.optionsStorage.validationary);

	// Object to share our data between JQuery blocks
	$.validationary = $.validationary || {};

	/**
	 * Adds ajax handlers to fields, which we want to validate with ajax.
	 *
	 * Fires on fileds with validate-ajax class
	 *
	 * @param   jQuery Object  $parent  Context where to start search from
	 *
	 * @return   void
	 */
	$.validationary.validateAjax = function ($parent)
	{
if (debug) console.log(Joomla.optionsStorage.validationary.forms);
		$.each(Joomla.optionsStorage.validationary.forms, function(formSelector, formOptions)
		{

			/**
			 * Either enables or disables submit button
			 *
			 * Our required fields can be in three states:
			 * - not yet edited (submit disabled)
			 * - error (submit disabled)
			 * - valid (submit enabled)
			 *
			 * @return   void
			 */
			var allowSubmit = function()
			{
				if (formOptions.submit_button_enabled)
				{
					return;
				}

				var disable = true;

				while (true)
				{
					var errorLoading = $form.find('.error, .loading').not('select');

					if (errorLoading.length >0 )
					{
						break;
					}

					var required = $form.find(formOptions.fields_selector_required).not('label').not('fieldset').not('div').not('.validated');

					if (required.length < 1) {
						disable = false;

						break;
					}

					break;
				}

				if (disable)
				{
					$submit.attr("disabled", "disabled");
					if ($fa)
					{
						$fa.removeClass('fa-check-circle-o');
						$fa.addClass('fa-close');
					}
				}
				else
				{
					$submit.removeAttr("disabled");
					$fa.addClass('fa-check-circle-o');
				}

				if ($fa)
				{
					$fa.removeClass('fa-spin fa-spinner');
				}

			};
if (debug) console.log(formSelector);
			var $form = $($parent.find(formSelector));
			if (!$form.length)
			{
				return;
			}

			var $submit = $($form.find('*[type="submit"]'));


			var $fa = false;

			switch (formOptions.fontawesome)
			{
				case 'include':
				case 'included':
					$fa = $($submit.find('.fa'));

					if ($fa.length < 1)
					{
						$submit.prepend(Joomla.optionsStorage.validationary.loading_snippet_light);
						$fa = $($submit.find('.fa'));
					}
					break;
				default:
					break;
			}
			allowSubmit();

			if (!$form.hasClass('validationary'))
			{
				$form.addClass('validationary');
			}
			else
			{
				return;
			}

			var fields_selectors = [formOptions.fields_selector_required,formOptions.fields_selector_only_validate];

			formOptions.fields_selector = [];

			var k = 0;
			for (i = 0; i < fields_selectors.length; i++)
			{
				if (fields_selectors[i].length > 0)
				{
					formOptions.fields_selector[k]  = fields_selectors[i];
					k++;
				}
			}

			formOptions.fields_selector = formOptions.fields_selector.join(',');

			var $fields = $form.find(formOptions.fields_selector).not('label').not('fieldset').not('div').not('.validation_setup');
			var $fields_required = $form.find(formOptions.fields_selector_required).not('label').not('fieldset').not('div');

			$fields_required.data('required', true);

			var $fields_only_validate = $form.find(formOptions.fields_selector_only_validate).not('label').not('fieldset').not('div');

			$fields_only_validate.data('required', false);
			if (!$fields.length)
			{
				return;
			}

			// $form.addClass('validationary');

			var timer;

			// Here we determins which response to use (one or both)
			// Currently this is hardcoded. Do we need to make it optional?
			var usePopover = true;

			// This option was used previously, but it's not used right now.
			// Not removed in purpose of possible future use
			var useMessageUnderField = false;

			var popoverPlacement;


			switch (Joomla.optionsStorage.validationary.behavior)
			{
				case 'bootstrap3':
					popoverPlacement = 'auto';
					break;
				default:
					popoverPlacement = 'right';
			}

			/**
			 * Restore original field state
			 *
			 * Removes error/invalid/valid classes,
			 * tries to restore original popover
			 *
			 * @param  jQuery object   Field to be unvalidated
			 *
			 * @return   bool          void
			 */
			var makeFieldUntouched = function($this)
			{
				if (useMessageUnderField)
				{
					$response.html();
				}

				$this.removeClass('validated invalid error loading');

				if ($this.hasClass('hasPopover') && $this.popoverWasChanged)
				{
						var $label = $this.parent().find('label');
						if ($label.length < 0)
						{
							$this.popover();
							return;
						}

						var opts = {
							title: '',
							placement: popoverPlacement,
							trigger: 'hover',
							html: 'true',
						};

						opts.title = $label.attr('title') || $label.data('original-title');

						if (!opts.title || opts.title.length < 0)
						{
							return;
						}
						var tmp = opts.title.split('</strong><br />');
						if (tmp.length > 1)
						{
							opts.title = tmp[0] + '</strong>';
							opts.content = tmp[1];
						}

						$this.data('toggle', 'popover');
						$this.popover(opts).popover().popover('hide');
				}
				allowSubmit();
			};

			/**
			 * Just a proxy to run per-field validation on a list of fileds
			 *
			 * @param   array  $elements  Array of fields to be ajax-validated
			 *
			 */
			var validateFields = function($elements)
			{
				for (i = 0; i < $elements.length; i++)
				{
					validateField(null, $($elements[i]));
					// continue;
				}
				allowSubmit();
			};

			/**
			 * Validates a field using Ajax
			 *
			 * Adds/removes classes depending on the validation result.
			 * Takes care of running validation if needed only
			 * - if the field was changed
			 * - not at every key press (using timer)
			 * Runs tied validations if needed (e.g. on change email1 rerun
			 * validation on email2)
			 *
			 * @param   event          event     Event, just in case
			 * @param   jQuery object  $element  Object being validated
			 *
			 * @return   void
			 */
			var validateField = function (event, $element)
			{
				var $this = $element || $(this);

				// We use this class to show that the field has already a validation event
				$this.addClass('validation_setup');

			// Special behaviour for a select
				var tagName = $this.prop("tagName");

				if (tagName === 'SELECT' && $this.data('required'))
				{
					var $chzn = $this.next('.chzn-container') || false;
					var $chzn = $this.parent().find('.chzn-container') || false;

					if ($chzn.length)
					{
						$chzn.removeClass('error');
						$chzn.removeClass('validated');
					}
					$this.parent().removeClass('error');

					if (!$this.val().length)
					{
						$this.addClass('error');

						if ($chzn.length)
						{
							$chzn.addClass('error');
						}
						else
						{
							$this.parent().addClass('error');
						}
					}
					else
					{
						$this.addClass('validated');

						if ($chzn.length)
						{
							$chzn.addClass('validated');
						}

					}
					allowSubmit();
					return;
				}

				// Do not run if nothing was changed in the field
				var previous_value = $this.data('previous_value');

				if (previous_value == $this.val())
				{
					return;
				}

				// Store field to later know if it was changed
				$this.data('previous_value', $this.val());

				// Abort running AJAX
				var xhr = $this.data('xhr');

				if (xhr)
				{
					$this.removeClass('loading');

					xhr.abort();
				}

				$this.popoverWasChanged = $this.popoverWasChanged || false;

				if ($this.attr('name').indexOf("jform[") < 0) {
					return;
				}

				var $response;

				if (useMessageUnderField)
				{
					$response = $this.parent().find(".response");
				}

				// Do not run validation on empty field
				// ~ if (
					// ~ (!$this.data('required') && !$this.val().length)
					// ~ ||
					// ~ ($this.data('required') && !$this.val().length && !$this.data('previous_value')))
				// ~ {

				// If value is set to nothing - make field as if it was before any change
				// and update submit button state
				if (!$this.val().length)
				{
					makeFieldUntouched($this);
					allowSubmit();
					return;
				}

				// Do not fire immediately
				if (timer) {
					if (event) {
						clearTimeout(timer);
					}
				}

				timer = setTimeout(function(){
					if (!$this.data('required') && !$this.val().length)
					{
						makeFieldUntouched($this);
						allowSubmit();
						return;
					}

					// Remove from the form unneeded `option` and `task` fields
					// (otherwise the form uses them as a post target)
					// and add needed for validation fields
					var form = $form.serializeArray();
					var form_task;
					var form_option;
					for (i = 0; i < form.length; i++)
					{
						if (form[i].name == 'option' || form[i].name == 'task')
						{
							if (form[i].name == 'task')
							{
								form_task = form[i].value;
							}
							if (form[i].name == 'option')
							{
								form_option = form[i].value;
							}
							index = form.indexOf(form[i]);
							if (index > -1) {
									form.splice(index, 1);
									i = i-1;
							}
						}
					}
					form = form.concat([
							{name: "form_task", value: form_task},
							{name: "form_option", value: form_option},
							{name: "field_to_validate", value: $this.attr('name')},
							{name: "rule_number", value: formOptions.rule_number}
					]);

					// Remove popover and response output
					if (useMessageUnderField)
					{
						if(!$response.length)
						{
							$('<div class="response"></>').insertAfter($this);
							$response = $this.parent().find(".response");
						}

						$response.data('loading-text', Joomla.optionsStorage.validationary.loading_snippet);
						$response.button('loading');
						$response.removeClass('error');
					}

					if (usePopover)
					{
						$this.addClass('loading');
						$this.popover('destroy');
						$this.popoverWasChanged = true;
					}

					$this.removeClass('error validated');
					// Set submit button to show the form is being validated
					if ($fa)
					{
						$fa.addClass('fa-spin fa-spinner');

						if (!formOptions.submit_button_enabled)
						{
							$submit.attr("disabled", "disabled");
						}
					}

					// Make validation post
					//$.post(Joomla.optionsStorage.validationary.ajax_url + '&dsa=' + Math.random(),
					xhr = $.post(Joomla.optionsStorage.validationary.ajax_url,
						form,
						function (response, success, dataType)
						{
							// Treat any text as error
							var message;
							var failed = false;

							if (dataType.getResponseHeader("content-type") == 'text/html')
							{
								messsage = response;
								failed = true;
							}
							else
							{
								message = response.message;
								if (!response.success )
								{
									failed = true;
								}
							}

							$this.removeClass('loading');

							if (failed === true)
							{
								$this.addClass('error');

								if (useMessageUnderField)
								{
									$response.addClass('error');
									$response.html(message);
								}

								if (usePopover)
								{
									var opts = {
										title: message,
										placement: popoverPlacement,
										trigger: 'hover',
										html: 'true',
									};

									if (response.messages)
									{
										opts.content = '';

										$.each(response.messages, function(type, msgs){
											var alertsuffix = '';

											// Convert Joomla BS 2 message types into BS 3
											if (formOptions.useAlerts)
											{
													switch (Joomla.optionsStorage.validationary.behavior)
													{
														case 'bootstrap3':
															switch (type)
															{
																case 'message':
																	alertsuffix = '';
																	break;
																case 'notice':
																	alertsuffix = 'info';
																	break;
																case 'warning':
																	alertsuffix = 'warning';
																	break;
																case 'error':
																	alertsuffix = 'danger';
																	break;
																default:
															}
															break;
														default:
															alertsuffix = type;
													}
											}

											var classes = '';
											if (alertsuffix.length)
											{
												classes = 'alert alert-' + alertsuffix;
											}

											opts.content = opts.content + '<div class="' + classes + '" role="alert"><ol><li>' + msgs.join('</li><li>') + '</li></ol></div>';
										});
									}
									$this.data('toggle', 'popover');
									$this.popover(opts).popover('show');
									$this.popoverWasChanged = true;
								}
							}
							else
							{
								makeFieldUntouched($this);

								$this.addClass('validated');
							}

							// If the AJAX call returned some field to be rechecked
							// (e.g. on change email1 we need to revalidated email 2)
							if (response.data && response.data.reCheckFields && event)
							{
								// Find fields to be revalidated
								var end_glue = '"]';
								var glue = ' input[name="';
								var selector = glue + response.data.reCheckFields.join(end_glue + ', ' + glue) + end_glue;
								var $elements = $parent.find(selector);
								$elements.data('previous_value', '');

								// And revalidated the fields
								validateFields($elements);
							}
							else
							{
								// Enable/disable submit button based on validation results
								allowSubmit();
							}
						});
						$this.data('xhr', xhr);
				}, 500);
			};

			// Attach the function on events
			// $field.on('keyup keypress blur change', function () {

			$fields.on('blur keyup datechange ', validateField );
			$fields.on(' change', function (){
						validateField(null, $(this));
			});

			// Run on page reload
			validateFields($fields);

		});
	};

	$.validationary.validateAjax($('body'));

});



