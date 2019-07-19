# WireMail: Mail Router

A ProcessWire 3.x WireMail module that sends email through other WireMail modules based 
upon configurable rules.

For this module to be useful, you should ideally have at least one other WireMail module installed
so that there is more than one way to send email in your system. 


## About the module

This module was originally built because I was having trouble with mailer services sometimes 
getting temporary blocked by Yahoo.com, Hotmail.com and others, resulting in large amounts of 
non-delivered email. This module enables you to route such mails to other services when you 
want to, ensuring they can still be delivered. 

The module also enables you to control delivery based upon any other values in the email or 
email headers, as well as designate primary and secondary WireMail modules to handle email 
delivery when one or another fails.

The module was built as part of the ProMailer package but seems like it could be useful in a 
lot of other situations too, so has been released on its own. 


## How to install

1. Copy the module files into: **/site/modules/WireMailRouter/**

2. In the ProcessWire admin, click to: Modules > Refresh

3. On the Modules “Site” tab, click “Install” for: WireMail > Mail Router

4. On the configuration screen, select a **Primary Mailer** (and optionally a secondary 
   mailer) and save. (You’ll configure rules a bit later in the “Configuration” section) 

5. Set *WireMailRouter* as your default mailer by editing **/site/config.php** and 
   adding the following line: 
   ~~~~~
   $config->wireMail('module', 'WireMailRouter'); 
   ~~~~~
   
The module is now installed and ready for sending email. To configure the rules that 
determine which mailer to use for any given email, move on to the Configuration section
below: 


## Configuration

The WireMailRouter module configuration screen lets you specify text-matching rules for
each WireMail module that you have installed. It also lets you specify rules for when to
use the core WireMail (PHP mail) and when an email should automatically fail or be 
skipped. 

For each input in the Rules section, you should specify one rule per line. The rule can 
either be plain text to match or it can be a regular expression for more powerful matching 
options. 

By default all rules are matching the “to” email address of a given message. However you
can also match other email properties when needed (details further below). 

If rules for multiple mailers match, only the first matched mailer will be used (in the 
order shown on the configuration screen). 

### Basic text matching rules

For most use cases, the basic text matching rules are likely to be adequate. So if this
section serves your needs, then you likely don't need to read beyond this section. 

Each rule may be any text to match, anywhere in the email address. Rules are not case 
sensitive. The rule `@yahoo.com` matches any email address at yahoo.com and the rule `bob` 
would match any email address that contains the letters “bob”, anywhere. 

Because basic text matching matches anywhere in the email address, if you want to match 
a specific domain, it is good to ensure there is a `@` in your rule. For example, 
`@hotmail.com` would be what you'd want to use if your rule intends to route emails 
addressed to hotmail.com emails because a rule of just `hotmail.com` (without the @) 
would also match `myhotmail.com`.

Some domains might have multiple possible TLDs. For instance, Yahoo email addresses
come in many flavors, like yahoo.com, yahoo.co.uk, yahoo.ca, yahoo.es, and so on. So 
if you wanted to match all of those, you'd want to use the rule without specifying 
the TLD, like this: `@yahoo.` (trailing period intentional). Of course, this would 
also match any email address using “yahoo” as the subdomain, but such cases seem 
unlikely. 

If you need more powerful matching capabilities, then you'd want to use the regular 
expression matching rules discussed below. 

### Regular expression matching rules

To perform more specific matches, or to match multiple domains, TLDs or subdomains in 
a single rule, you might find this best achieved with a regular expression (regex). 

A regex is assumed whenever the rule contains certain characters that never appear in 
email addresses (regex start/end delimiters are optional). Matching is done with PCRE
compatible regular expressions, except that ours are NOT case sensitive unless you 
specify your own starting/ending delimiters. Behind the scenes, the match is performed
with PHP’s [preg_match](https://www.php.net/manual/en/function.preg-match.php) function. 

Below are some examples of regular-expression based matching rules: 

- `^bob@domain\.com$`   
   Matches only the exact email “bob@domain.com”. 

- `@gmail\.com$`   
   Matches all email addresses ending with “gmail.com”. 

- `@(hotmail|outlook|live)\.com$`   
   Matches “hotmail.com”, “outlook.com” and “live.com” email addresses. 
  
- `(@|\.)(yahoo|aol)\.[.a-z]+$`   
   Matches yahoo and aol emails with optional subdomain and any TLD/extension. 

### Matching other email properties

For more advanced or specific use cases, you can match email properties other than the 
email “to” address. To do so, specify your matching rule in the format: `property:rule`. 

For instance, you can match any email with a subject containing the word “receipt” with: 
`subject:receipt`. To match emails where the subject BEGINS with the word “receipt” you 
would want to use a regular expression for the rule part: `subject:^receipt`. 

To match emails where the FROM email (rather than TO email) contains “@mydomain.com” you 
would use: `from:@mydomain.com`. 

To match emails where the BODY contains “booking request” you would use:
`body:booking request`, or for the HTML body you would use: `bodyHTML:booking request`.

Non-default properties that you can match include the following:

- toName
- from
- fromName
- replyTo
- replyToName
- subject
- body
- bodyHTML
- header

If matching the “header” property note that it performs the match upon all of the email
headers where each header string is in the format `header-name: header-value`.

---
Copyright 2019 by Ryan Cramer
