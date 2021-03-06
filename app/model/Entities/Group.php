<?php

namespace App\Model\Entities;

class Group extends AbstractEntity 
{
    
    public $id = null;
    public $slug = null;
    public $name = null;
    public $email = null;
    public $shortcut = null;
    public $mainColor = null;
    public $colorScheme = null;
    public $colorSchemeId = null;
    public $numberOfStudents = null;
    public $groupType = null;
    public $newMessages = null;
    //teacher a owner jsou ti stejný - skupina má zatím pouze jednoho učitele
    public $teacher = null;
    public $owner = null;
    public $relation = null;
    
    public $description = null;
    public $room = null;
    public $subgroup = null;    
    public $studentsCount = 0;
	
    public $interCode = null;
    public $publicCode = null;
    public $shareByLink = null;
    public $shareByCode = null;
    public $deleted = null;
    public $classification = null;
    public $archived = null;
    
    public $statistics = null;
    public $userJoin = null;
    
    public $activePeriodId = null;
    public $activePeriodName = null;
    public $isMy = null;
    
    public $showDeleted = false;
    
    public $pr_user_msg_create = 0;
    public $pr_share_msg = 0;
    
    protected $mapFields = [
        'id' => 'id',
        'name' => 'name',
        'shortcut' => 'shortcut',
        'scheme_code' => 'colorScheme',
        'main_color' => 'mainColor',
        'slug' => 'slug',
        'is_my' => 'isMy'
    ];

}
