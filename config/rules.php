<?php
/**
 * AI Pravidla - automaticky generováno
 * Generated: 2025-11-22 15:41:32
 */

declare(strict_types=1);

return array (
  'system' =>
  array (
    'version' => '2.0',
    'created' => '2025-11-19',
    'dynamic_rules' => true,
  ),
  'rules' =>
  array (
    'koding_standardy' =>
    array (
      'phpdoc_povinne' => true,
      'prisne_typy' => true,
      'csrf_ochrana' => true,
	  'namespaces_povinne' => true,
    ),
    'logger_nastaveni' =>
    array (
      'logger_sluzby' => true,
      'logger_kontrolery' => true,
      'logger_autentizace' => true,
    ),
    'ai_chovani' => 
    array (
      'kdyz_nejsem_jisty' => 'zeptej_se_na_upresneni',
      'osetrovani_chyb' => 'vyjimky',
    ),
  ),
  'rule_categories' => 
  array (
    'koding_standardy' => 
    array (
      'label' => '🎯 Kódové Standardy',
      'type' => 'checkbox_group',
      'rules' => 
      array (
        'phpdoc_povinne' => 
        array (
          'label' => 'PHP Doc komentáře povinné',
          'type' => 'boolean',
          'default' => true,
        ),
        'prisne_typy' => 
        array (
          'label' => 'Strict types povinné',
          'type' => 'boolean',
          'default' => true,
        ),
        'csrf_ochrana' => 
        array (
          'label' => 'CSRF ochrana povinná',
          'type' => 'boolean',
          'default' => true,
        ),
		'namespaces_povinne' =>  // ← PŘIDÁNO!

			array (
      			'label' => 'Namespaces povinné',
      			'type' => 'boolean',
      			'default' => true,
    		),
      ),
    ),
    'logger_nastaveni' => 
    array (
      'label' => '📝 Logger Nastavení',
      'type' => 'checkbox_group',
      'rules' => 
      array (
        'logger_sluzby' => 
        array (
          'label' => 'Logger pro služby',
          'type' => 'boolean',
          'default' => true,
        ),
        'logger_kontrolery' => 
        array (
          'label' => 'Logger pro kontrolery',
          'type' => 'boolean',
          'default' => true,
        ),
        'logger_autentizace' => 
        array (
          'label' => 'Logger pro autentizaci',
          'type' => 'boolean',
          'default' => true,
        ),
      ),
    ),
    'ai_chovani' => 
    array (
      'label' => '🤖 AI Chování',
      'type' => 'select_group',
      'rules' => 
      array (
        'kdyz_nejsem_jisty' => 
        array (
          'label' => 'Když si nejsem jistý',
          'type' => 'select',
          'options' => 
          array (
            'zeptej_se_na_upresneni' => 'Zeptej se na upřesnění',
            'navrhni_moznosti' => 'Navrhni možnosti',
            'pouzij_bezpecne_vychozi' => 'Použij bezpečné výchozí',
          ),
          'default' => 'zeptej_se_na_upresneni',
        ),
        'osetrovani_chyb' => 
        array (
          'label' => 'Způsob ošetřování chyb',
          'type' => 'select',
          'options' => 
          array (
            'vyjimky' => 'Výjimky (Exceptions)',
            'navratove_hodnoty' => 'Návratové hodnoty',
            'logovani' => 'Pouze logování',
          ),
          'default' => 'vyjimky',
        ),
      ),
    ),
  ),
  'context_rules' => 
  array (
    'vzdy_poskytni_strukturu' => true,
    'zahrni_vztahy_souboru' => true,
    'poznamenej_důležité_závislosti' => true,
  ),
);
?>