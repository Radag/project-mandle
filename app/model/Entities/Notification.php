<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Model\Entities;
/**
 * Description of Notification
 *
 * @author Radaq
 */
class Notification extends AbstractEntity {
    
    public $id = null;
    public $title = null;
    public $text = null;
    public $icon = null;
    public $idUser = null;
    public $type = null;
    public $data = null;
    public $isRead = null;
}
