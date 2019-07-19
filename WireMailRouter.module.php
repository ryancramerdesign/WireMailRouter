<?php namespace ProcessWire;

/**
 * Mail Router
 * 
 * WireMail module that sends email through other WireMail modules based upon configurable rules.
 * 
 * For this module to be useful, you should ideally have at least one other WireMail module installed
 * so that there is more than one way to send email in your system. 
 * 
 * This module was originally built because I was having trouble with mailer services sometimes 
 * getting temporary blocked by Yahoo.com, Hotmail.com and others, resulting in large amounts of 
 * non-delivered email. This module enables you to route such mails to other services when you 
 * want to, ensuring they can still be delivered. It also enables you to control delivery based 
 * upon any other values in the email or email headers, as well as designate primary and secondary
 * WireMail modules to handle email delivery. 
 * 
 * The module was built as part of the ProMailer package but seems like it could be useful in a 
 * lot of other situations too, so has been released on its own. 
 * 
 * Copyright 2019 by Ryan Cramer for ProcessWire 3.x (MPL 2.0)
 * 
 * @property string $mailer1 Primary mailer to use when email does not match any rules
 * @property string $mailer2 Secondary mailer to use when email does not match any rules and primary mailer fails
 * @property string $rulesWireMail This property, or any beginning with this name
 * @property string $rulesFail Rules for forced fail
 * @property string $rulesSkip Rules for forced skip
 * @property bool|int $useLog Log when rules match?
 * 
 * @method array chooseMailer($value)
 * @method bool valuesMatchRule(array $values, $rule)
 * 
 */

class WireMailRouter extends WireMail implements Module, ConfigurableModule {

	/**
	 * Module info
	 * 
	 * @return array
	 * 
	 */
	public static function getModuleInfo() {
		return array(
			'title' => 'Mail Router', 
			'summary' => 'WireMail module that sends email through other WireMail modules based upon configurable rules.',
			'version' => 1,
		);
	}

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->set('mailer1', '');
		$this->set('mailer2', '');
		$this->set('rulesWireMail', '');
		$this->set('rulesFail', '');
		$this->set('rulesSkip', '');
		$this->set('useLog', true);
		parent::__construct();
	}
	
	public function set($key, $value) {
		// prevent mailer1 and mailer2 from being the same
		if($key === 'mailer2' && $value && $value === $this->get('mailer1')) $value = '';
		if($key === 'mailer1' && $value && $value === $this->get('mailer2')) parent::set('mailer2', '');
		return parent::set($key, $value); 
	}
	
	/**
	 * Send the email
	 *
	 * @return int Returns a positive number (indicating number of addresses emailed) or 0 on failure.
	 *
	 */
	public function ___send() {
		$numSent = 0;
		
		foreach($this->mail['to'] as $toEmail) {
			$toName = isset($this->mail['toName'][$toEmail]) ? $this->mail['toName'][$toEmail] : '';
			$numSent += $this->sendTo($toEmail, $toName);
		}
		
		return $numSent;
	}

	/**
	 * Get the requested mailer or fallback to another if not available
	 * 
	 * @param string $mailerName
	 * @param bool $fallback Allow falling back to other mailers when requested mailer not available? (default=true)
	 * @return WireMail|null
	 * 
	 */
	protected function getMailer($mailerName = '', $fallback = true) {
		
		$mail = $this->wire('mail'); /** @var WireMailTools $mail */
		$mailer = $mailerName ? $mail->new(array('module' => $mailerName)) : $mail->new();
		
		if(!$mailer && $fallback) { 
			if($mailerName != $this->mailer1) {
				// if non primary mailer did not load, fallback to primary mailer
				$mailer = $mail->new(array('module' => $this->mailer1));
			}
			if(!$mailer && $this->mailer2 && $mailerName != $this->mailer2) {
				// if still no mailer and secondary mailer available, attempt fallback to it
				$mailer = $mail->new(array('module' => $this->mailer2));
			}
		}
		
		if($mailer) {
			// copy properties from this WireMail instance to our new WireMail ($mailer) instance
			// except for anything to do with the "to" email or name
			foreach($this->mail as $key => $value) {
				if($key === 'to' || $key === 'toName' || $key === 'attachments' || $key === 'param') continue;
				$mailer->set($key, $value);
			}
			foreach($this->mail['attachments'] as $filename => $value) {
				$mailer->attachment($value, $filename);
			}
			foreach($this->mail['param'] as $param) {
				$mailer->param($param);
			}
		}
		
		return $mailer;
	}

	/**
	 * Create new WireMail instance to send to just a particular email address and name
	 * 
	 * @param string $toEmail
	 * @param string $toName
	 * @return int 1 on success, 0 on fail
	 * 
	 */
	protected function sendTo($toEmail, $toName) {

		$numSent = 0;
		$sentMailerName = '';
		$mailer = null;
		
		list($mailerName, $matchRule) = $this->chooseMailer($toEmail);
		
		if($mailerName === 'Fail') {
			// forced fail
			
		} else if($mailerName === 'Skip') {
			// forced skip
			$numSent = 1;
			
		} else {
			// get mailer module
			$mailer = $this->getMailer($mailerName);
		}
		
		if($mailer) {
			// send message
			$numSent = $mailer->to($toEmail, $toName)->send();
			$sentMailerName = $mailer->className();

			// attempt fallback to primary mailer if send failed on another mailer
			if(!$numSent && $this->mailer1 && $sentMailerName != $this->mailer1) {
				$mailer = $this->getMailer($this->mailer1);
				if($mailer) {
					$numSent = $mailer->to($toEmail, $toName)->send();
					$sentMailerName = $mailer->className();
				}
			}

			// attempt fallback to secondary mailer if send failed on primary and/or some other
			if(!$numSent && $this->mailer2 && $mailerName != $this->mailer2 && $sentMailerName != $this->mailer2) {
				$mailer = $this->getMailer($this->mailer2);
				if($mailer) {
					$numSent = $mailer->to($toEmail, $toName)->send();
					$sentMailerName = $mailer->className();
				}
			}
		}
	
		// log mail activity 
		if($this->useLog) {
			if($mailer && $sentMailerName != $mailerName) $mailerName = "$sentMailerName (fallback from $mailerName)";
			$status = $numSent ? 'sent' : 'failed';
			$logLine = "$mailerName $status $toEmail ";
			if(!empty($matchRule)) $logLine .= "- matched: $matchRule";
			$this->log($logLine);
		}

		return $numSent;
	}

	/**
	 * Does the given rule text contain a regular expression?
	 * 
	 * Returns false if not. Returns true if non-delimited regex. Returns delimiter if delimited regex. 
	 * 
	 * @param string $rule
	 * @return bool|string
	 * 
	 */
	protected function isRegexRule($rule) {
		
		$is = false;
		$chars = array('/', '\\', '*', '(', '[', '{', '^', '$', '!', '#', '%', '~');
		$delims = array('/', '!', '~', '#', '%', '{'); 
		
		foreach($chars as $c) {
			if(strpos($rule, $c) === false) continue;
			$is = true;
			foreach($delims as $delim) {
				// determine delimiter, if in use
				if(strpos($rule, $delim) === 0 && strrpos($rule, $delim) > 0) {
					$is = $delim;
					break;
				}
			}
			break;
		}
		
		return $is;
	}
	
	/**
	 * Do any of the given values match the given rule?
	 * 
	 * @param array $values Array of text values
	 * @param string $rule Rule to match
	 * @return bool
	 * 
	 */
	protected function ___valuesMatchRule(array $values, $rule) {
		
		$match = false;
		$isRegex = $this->isRegexRule($rule);
		
		if($isRegex) {
			// regular expression (make delimited if not already)
			if($isRegex === true) $rule = "/$rule/i"; 
			
		} else {
			// regular text rule
			$rule = strtolower($rule);
		}
		
		// determine if rule matches
		foreach($values as $value) {
			
			
			if($isRegex) {
				// regular expression
				$match = preg_match($rule, $value);
			} else {
				// non-regex match
				$value = strtolower($value);
				$match = $value === $rule || strpos($value, $rule) !== false;
			}

			// if a match is found, exit early
			if($match) break;
		}
		
		return $match;
	}
	
	/**
	 * Does the given text match any of the given rules?
	 * 
	 * @param string $value
	 * @param array $rules Array of rules (strings)
	 * @return bool|string Returns matching rule (string) when value matches, or boolean false when not
	 * 
	 */
	protected function valueMatchesRules($value, array $rules) {
		
		$match = false;
		
		foreach($rules as $rule) {

			$rule = trim($rule);
			if(!strlen($rule)) continue;
			$matchRule = $rule; // unmodified rule for return value, when requested

			if(strpos($rule, ':')) {
				// rule specifies it should match something specific other than $text
				// in this case, we ignore $value (an email address) and pull our own value from the mailer
				list($property, $propertyRule) = explode(':', $rule, 2);
				if(isset($this->mail[$property])) {
					$rule = trim($propertyRule);
					$value = $this->mail[$property];
				}
				if(is_array($value) && ($property === 'header' || $property === 'headers')) {
					// if headers requested, create values that are in the format: "headerName: headerValue"
					foreach($value as $k => $v) {
						$value[$k] = "$k: $v";
					}
				}
			}
			
			$values = is_array($value) ? $value : array($value);
			
			if($this->valuesMatchRule($values, $rule)) {
				$match = $matchRule;
				break;
			}
		}
		
		return $match;
	}

	/**
	 * Get the name of the WireMail module to use for the given value or email address and the matching rule
	 * 
	 * @param string $value Email address for message to be sent
	 * @return array Returns array of ['mailer name', 'match rule'], where 'match rule' will be empty or boolean false if no rule matched
	 * 
	 */
	protected function ___chooseMailer($value) {
		
		$mailerName = $this->mailer1;
		$matchRule = false;
		
		foreach(array_keys($this->getMailers(true)) as $name) {
			$rules = $this->get("rules$name");
			if(empty($rules)) continue;
			$rules = explode("\n", $rules);
			$matchRule = $this->valueMatchesRules($value, $rules);
			if(empty($matchRule)) continue;
			$mailerName = $name;
			break;
		}
		
		return array($mailerName, $matchRule);
	}
	
	/**
	 * Get array of all mailers that can be used
	 * 
	 * @param bool $extras Include the 'Skip' and 'Fail' extras? (default=false)
	 * @return array Keys are mailer names and values are titles
	 * 
	 */
	protected function getMailers($extras = false) {
		
		$modules = $this->wire('modules'); /** @var Modules $modules */
		$thisName = $this->className();
		$mailers = array();
		
		if($extras) $mailers = array(
			'Fail' => $this->_('Always fail (record error)'),
			'Skip' => $this->_('Always skip (pretend success)'),
		);
		
		foreach($modules->findByPrefix('WireMail', 1) as $info) {
			$mailerName = $info['name'];
			if($mailerName === $thisName) continue;
			$mailers[$mailerName] = $info['title'];
		}
		
		$mailers['WireMail'] = $this->_('WireMail core (uses PHP mail function)');
		
		return $mailers;
	}
	
	/**
	 * Run the given array of email addresses through currently defined rules and return array of results
	 *
	 * @param array $tests Email addresses to test
	 * @return array
	 *
	 */
	public function runTests(array $tests) {

		$results = array();
		$noMatchLabel = $this->_('None');

		foreach($tests as $test) {

			$test = trim($test);
			if(empty($test)) continue;

			if(strpos($test, '=')) {
				// set a property
				list($property, $value) = explode('=', $test);
				if(isset($this->mail[$property])) {
					$this->$property($value);
				} else {
					$this->error("Unknown mail property: $property");
				}
				continue;
			}

			list($mailerName, $rule) = $this->chooseMailer($test);
			if(empty($rule)) $rule = $noMatchLabel;

			$result = array(
				'email' => $test,
				'mailer' => $mailerName,
				'rule' => $rule
			);

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		if(!$this->mailer1) {
			$this->mailer1 = $this->wire('mail')->new()->className();
			if($this->mailer1 == $this->className()) $this->mailer1 = 'WireMail';
		}
		
		$modules = $this->wire('modules'); /** @var Modules $modules */
		$mailers = $this->getMailers();
	
		// mailers allowed for primary or secondary mailer selection
		$f = $modules->get('InputfieldSelect'); /** @var InputfieldSelect $f */
		$f->attr('name', 'mailer1'); 
		$f->addOptions($mailers);
		$f->attr('value', $this->mailer1);
		$f->label = $this->_('Primary mailer');
		$f->icon = 'envelope-o';
		$f->description = $this->_('Mailer to use when it does not match any other mailer-specific rules below.'); 
		$f->required = true; 
		$f->columnWidth = 50;
		$inputfields->add($f);
		
		$f = $modules->get('InputfieldSelect'); /** @var InputfieldSelect $f */
		$f->attr('name', 'mailer2');
		$f->addOptions($mailers);
		$f->attr('value', $this->mailer2);
		$f->label = $this->_('Secondary mailer');
		$f->icon = 'envelope-o';
		$f->description = $this->_('Mailer to use when sending with primary mailer returns an error.');
		$f->columnWidth = 50;
		$inputfields->add($f);
	
		$fs = $modules->get('InputfieldFieldset'); /** @var InputfieldFieldset $fs */
		$fs->label = $this->_('Rules that determine what mailer should be used');
		$fs->icon = 'map-o';
		$fs->description = 
			'*' . $this->_('Basic usage:') . '* ' . 
			$this->_('Enter one rule per line in the Mailer-specific inputs below, where each rule matches an email “to” address.') . ' ' . 
			$this->_('Each rule may be any text to match, anywhere in the email address. Rules are not case sensitive.') . ' ' .
			$this->_('The rule `@yahoo.com` matches any email address at yahoo.com and `bob` would match any email address that contains the letters “bob”, anywhere.') . ' ' . 
			$this->_('If rules for multiple mailers match, only the first matched mailer will be used (in the order shown below).') . ' ' . 
			"\n\n" . 
			'*' . $this->_('Using regex rules:') . '* ' . 
			$this->_('To perform more specific matches, or to match multiple domains, TLDs or subdomains in a single rule, you’ll want to use a regular expression (regex).') . ' ' .
			$this->_('A regex is assumed whenever the rule contains certain characters that never appear in email addresses (regex start/end delimiters are optional).') . ' ' .
			sprintf($this->_('For example, the rule %s would match only the exact email “bob@domain.com”.'), '`^bob@domain\.com$`') . ' ' . 
			sprintf($this->_('The rule %s would match all email addresses ending with “gmail.com”.'), '`@gmail\.com$`') . ' ' . 
			sprintf($this->_('The rule %s would match “hotmail.com”, “outlook.com” and “live.com” email addresses.'), '`@(hotmail|outlook|live)\.com$`') . ' ' . 
			sprintf($this->_('The rule %s would match yahoo and aol emails with optional subdomain and any TLD/extension.'), '`(@|\.)(yahoo|aol)\.[.a-z]+$`') . ' ' . 
			"\n\n" . 
			'*' . $this->_('Matching other mail properties:') . '* ' . 
			$this->_('To match a mail property other than the email “to” address, specify in the format: `property:rule`.') . ' ' . 
			$this->_('For instance, you can match any email with a subject containing “receipt” with: `subject:receipt`.') . ' ' . 
			$this->_('To match emails where the subject *begins* with the word “receipt” you would use: `subject:^receipt`.') . ' ' . 
			$this->_('To match emails where the *from* contains “@mydomain.com” you would use: `from:@mydomain.com`.') . ' ' . 
			$this->_('To match emails where the *body* contains “booking request” you would use: `body:booking request` or for the HTML body: `bodyHTML:booking request`.') . ' ' . 
			'';
			
		$inputfields->add($fs);
		
		foreach($this->getMailers(true) as $mailerName => $mailerTitle) {
			$f = $modules->get('InputfieldTextarea'); /** @var InputfieldTextarea $f */
			$name = "rules$mailerName";
			$f->label = sprintf($this->_('Rules for: %s'), $mailerTitle);
			$f->description = sprintf($this->_('When an email matches these rules, it will force use of “%s”.'), $mailerName) . ' ';
			$f->set('themeOffset', 1);
			if($mailerName === 'Fail') {
				$f->description .= $this->_('The message will not be sent and an error will be recorded.');
			} else if($mailerName === 'Skip') {
				$f->description .= $this->_('The message will not be sent but a success code will be returned.');
			}
			$f->attr('name', $name);
			$f->attr('value', $this->get($name));
			$f->collapsed = Inputfield::collapsedBlank;
			$fs->add($f);
		}
		
		$f = $modules->get('InputfieldCheckbox'); /** @var InputfieldCheckbox $f */
		$f->attr('name', 'useLog');
		$f->label = $this->_('Log matching mailer rules? (Setup > Logs > wire-mail-router)');
		if($this->useLog) $f->attr('checked', 'checked');
		$inputfields->add($f);
		
		$f = $modules->get('InputfieldTextarea'); /** @var InputfieldTextarea $f */
		$f->attr('name', '_test_lines');
		$f->label = $this->_('Test rules on email addresses');
		$f->description =
			$this->_('Enter one email address per line to test them against your defined rules.') . ' ' .
			$this->_('After submitting the form, it will tell you what mailer was chosen and what rule matched.');
		$f->collapsed = Inputfield::collapsedYes;
		$f->icon = 'flask';
		$inputfields->add($f);

		$table = $modules->get('MarkupAdminDataTable'); /** @var @var MarkupAdminDataTable $table */
		$tests = $this->wire('input')->post('_test_lines');
		if($tests) {
			$tests = explode("\n", $tests);
			$results = $this->runTests($tests);
			foreach($results as $result) {
				$table->row(array_values($result));
			}
			if(count($results)) {
				$table->headerRow(array(
					$this->_('Email'), 
					$this->_('Matched Mailer'), 
					$this->_('Matched Rule')
				));
				$this->message($this->_('Test Results') . $table->render(), Notice::allowMarkup);
			}
		}
	}

}
