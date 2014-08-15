<?php
require_once __DIR__.'/../../../../vendor/autoload.php';
$app = new Silex\Application();
$app->register(new \Igorw\Silex\ConfigServiceProvider(__DIR__."/../config/config.yml"));  

include_once __DIR__ . '/../../../../app.config.php';
//include_once __DIR__ . '/../config/config.php';

function addFieldStr($fieldName, $field, $lang = null)
{
  $str =' `' . $fieldName . ($lang ? '_' . $lang : '') . '`';
  $str .= ' ' . $field['sqlType'];
  if (isset($field['sqlSize']))
  {
    $str .= '(' . $field['sqlSize'] . ')';
  }

  return $str;
}

if (count($app['gallery.objects'])) {
  
}
foreach($app['gallery.objects'] as $objectType => $object)
{
  $query = 'create table `' . $objectType . '` (' . "\n";
  $query .= 'id int(11) NOT NULL AUTO_INCREMENT,' . "\n";
  $query .= '`created_at` datetime NOT NULL,' . "\n";
  $query .= '`updated_at` datetime NOT NULL,' . "\n";
  $query .= 'PRIMARY KEY (`id`),' . "\n";

  $isFirst = true;
  $fieldStrs = array();
  foreach($app['gallery.objects'][$objectType]['fields'] as $fieldName => $field)
  {
    if (isset($field['multilingual']) && $field['multilingual'])
    {
      foreach(array_keys($app['gallery.languages']) as $lang)
      {
        $fieldStrs[] = addFieldStr($fieldName, $field, $lang);
      }
    }
    else
    {
      $fieldStrs[] = addFieldStr($fieldName, $field);
    }
  }
  $query.= implode(",\n", $fieldStrs);
  $query .= "\n);";

  echo $query;
  
  // media
  if (isset($app['gallery.objects'][$objectType]['media'])) {  
    $query = '
    CREATE TABLE `' . $objectType . '_media` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `type` varchar(255) NOT NULL,
      `url` varchar(255) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `position` int(11) NOT NULL,
      `' . $objectType . '_id` int(11) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
    ';
    echo $query;
  }
  
  // docs
  if (isset($app['gallery.objects'][$objectType]['doc'])) {
    $query = '
    CREATE TABLE `' . $objectType . '_doc` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `type` varchar(255) NOT NULL,
      `url` varchar(255) NOT NULL,
      `preview_url` varchar(255) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `position` int(11) NOT NULL,
      `' . $objectType . '_id` int(11) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
    ';
    echo $query;    
  }
  
  // tag classes
  if (isset($app['gallery.objects'][$objectType]['tag.classes']) && $app['gallery.objects'][$objectType]['tag.classes'])
  {
    $query = 'CREATE TABLE `' . $objectType . '_potential_tag` (
      id int(11) NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      category varchar(255) NOT NULL,
      PRIMARY KEY(`id`)
    );' . "\n";
    echo $query;

    $query = 'CREATE TABLE `' . $objectType . '_tag` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      PRIMARY KEY (`id`),
      ' . $objectType . '_id int(11),
      ' . $objectType . '_potential_tag_id int(11)
    );' . "\n";
    echo $query;

  }
  
  // crossing classes
  if (isset($app['gallery.objects'][$objectType]['crossings']) && ($crossings = $app['gallery.objects'][$objectType]['crossings']))
  {
    foreach ($crossings as $key => $crossing) {
      $query = 'CREATE TABLE `' . $objectType . '_' . $crossing . '` (
        id int(11) NOT NULL AUTO_INCREMENT,
        `' . $objectType . '_id` int(11) NOT NULL,
        `' . $crossing . '_id` int(11) NOT NULL,
        PRIMARY KEY(`id`)
      );' . "\n";
      echo $query;      
    }

  }  

}

// PAGES
if (isset($app['gallery.pages']))
{
  foreach ($app['gallery.pages'] as $pageName => $data) {
    $fields = $data['fields'];
    $query = 'CREATE TABLE `' . $pageName. '` (' . "\n";
    $query .= '`id` int(11) NOT NULL AUTO_INCREMENT,';
    $query .= '  PRIMARY KEY (`id`)';
    $fieldStrs = array();
    if ($fields) {
      foreach($fields as $fieldName => $field) {
        if (isset($field['multilingual']) && $field['multilingual'])
        {
          foreach(array_keys($app['gallery.languages']) as $lang)
          {
            $fieldStrs[] = addFieldStr($fieldName, $field, $lang);
          }
        }
        else
        {
          $fieldStrs[] = addFieldStr($fieldName, $field);
        }    
      }      
      $query.= ',' . implode(",\n", $fieldStrs);
    }
    $query .= ');' . "\n";
    echo $query;
    
    $query = 'create table `' . $pageName . '_media` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `type` varchar(255) NOT NULL,
    `url` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `position` int(11) NOT NULL,
    `' . $pageName . '_id` int(11) NOT NULL,
    PRIMARY KEY (`id`)
    );';
    echo $query;
    
    // docs
    if (isset($app['gallery.pages'][$pageName]['doc'])) {
      $query = '
      CREATE TABLE `' . $pageName . '_doc` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `type` varchar(255) NOT NULL,
        `url` varchar(255) NOT NULL,
        `preview_url` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `position` int(11) NOT NULL,
        `' . $pageName . '_id` int(11) NOT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
      ';
      echo $query;    
    }    
  }
    
}
