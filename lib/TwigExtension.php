<?php
class TwigExtension extends \Twig_Extension
{
    public function getName() {
        return "twigextension";
    }

    public function getFilters() {
        return array(
            "french_date"        => new \Twig_Filter_Method($this, "french_date"),
        );
    }

    public function french_date($input, $lang) {
      if ($lang != 'fr') {
        return $input;
      }
      $input = str_replace(array(
        'JANUARY',
        'FEBRUARY',
        'MARCH',
        'APRIL',        
        'MAY',        
        'JUNE',
        'JULY',        
        'AUGUST',
        'SEPTEMBER',
        'OCTOBER',
        'NOVEMBER',
        'DECEMBER',
        'MONDAY',
        'TUESDAY',
        'WEDNESDAY',
        'THURSDAY',
        'FRIDAY',
        'SATURDAY',
        'SUNDAY',
      ),
      array(
        'JANVIER', 
        'FEVRIER', 
        'MARS', 
        'AVRIL',
        'MAI',
        'JUIN',
        'JUILLET',
        'AOÛT',
        'SEPTEMBRE',
        'OCTOBRE',
        'NOVEMBRE',
        'DECEMBRE',
        'LUNDI',
        'MARDI',
        'MERCREDI',        
        'JEUDI',
        'VENDREDI',
        'SAMEDI',
        'DIMANCHE',
      ),
      $input);
      return $input;
    }
}
