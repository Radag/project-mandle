<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Model\Entities;
/**
 * Description of Message
 *
 * @author Radaq
 */
class Classification {
    public $idClassification = null;
    public $idClassificationGroup = null;
    
    public $user = null;
    public $group = null;
    public $name = null;
    public $grade = null;
    public $lastChange = null;
    public $notice = null;
    public $classificationDate = null;
}
