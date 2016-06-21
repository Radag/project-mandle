<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Model;

use Nette;
use App\Model\Entities\Group;

/**
 * Description of MessageManager
 *
 * @author Radaq
 */
class GroupManager extends Nette\Object{
 
    
    /** @var Nette\Database\Context */
    private $database;


    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function setGroupVisited($user, $idGroup)
    {
        $this->database->query("UPDATE user_group SET LAST_VISIT=NOW() WHERE ID_USER=? AND ID_GROUP=?", $user->id, $idGroup);
    }
    
    
    public function getUserGroups($user)
    {
        $return = array();
        $yourGroups = $this->database->query("SELECT T1.ID_GROUP, T3.MAIN_COLOR, T2.NAME, T2.SHORTCUT, T2.GROUP_TYPE FROM (
            SELECT DISTINCT ID_GROUP FROM user_group WHERE ID_USER=?
            UNION 
            SELECT DISTINCT ID_GROUP FROM groups WHERE ID_TEACHER=?) T1
            LEFT JOIN groups T2 ON  T1.ID_GROUP = T2.ID_GROUP
            LEFT JOIN group_color_scheme T3 ON T2.COLOR_SCHEME=T3.ID_SCHEME", $user->id, $user->id)->fetchAll(); 
        
        if(!empty($yourGroups)) {
            foreach($yourGroups as $s) {
                $group = new Group;
                $group->id = $s->ID_GROUP;
                $group->name = $s->NAME;
                $group->shortcut = $s->SHORTCUT;
                $group->groupType = $s->GROUP_TYPE;
                $group->mainColor = $s->MAIN_COLOR;
                $return[$s->ID_GROUP] = $group;
            }
        }
        return $return;
    }

    public function getGroup($idGroup)
    {
        $group = $this->database->query("SELECT 
                T1.ID_GROUP,
                T1.NAME,
                T1.SHORTCUT,
                T2.NAME AS TEACHER_NAME,
                T2.SURNAME AS TEACHER_SURNAME,
                T3.MAIN_COLOR,
                T4.STUDENTS
        FROM groups T1
        LEFT JOIN user T2 ON T1.ID_TEACHER=T2.ID_USER
        LEFT JOIN group_color_scheme T3 ON T1.COLOR_SCHEME=T3.ID_SCHEME
        LEFT JOIN (SELECT COUNT(ID_USER) AS STUDENTS, ID_GROUP FROM user_group GROUP BY ID_GROUP) T4 ON T4.ID_GROUP=T1.ID_GROUP             
        WHERE T1.ID_GROUP=?", $idGroup)->fetch();

        $groupModel = new Group();
        $user = new Entities\User();
        $user->surname = $group->TEACHER_SURNAME;
        $user->name = $group->TEACHER_NAME;
        $groupModel->id = $group->ID_GROUP;
        $groupModel->name = $group->NAME;
        $groupModel->shortcut = $group->SHORTCUT;
        $groupModel->mainColor = $group->MAIN_COLOR;
        $groupModel->numberOfStudents = $group->STUDENTS;
        $groupModel->teacher = $user;
        
        return $groupModel;       
    }
    
    
    public function getGroups($user)
    {
        $return = array();
        $userGroups = $this->getUserGroups($user);
        
        if(!empty($userGroups)) {
            $groups = $this->database->query("SELECT 
                        T1.ID_GROUP,
                        T1.NAME,
                        T1.SHORTCUT,
                        T2.NAME AS TEACHER_NAME,
                        T2.SURNAME AS TEACHER_SURNAME,
                        T3.MAIN_COLOR,
                        T4.STUDENTS,
                        T5.NEW_MESSAGE,
                        T6.PATH,
                        T6.FILENAME
                FROM groups T1
                LEFT JOIN user T2 ON T1.ID_TEACHER=T2.ID_USER
                LEFT JOIN group_color_scheme T3 ON T1.COLOR_SCHEME=T3.ID_SCHEME
                LEFT JOIN (SELECT COUNT(ID_USER) AS STUDENTS, ID_GROUP FROM user_group WHERE ID_RELATION=2 GROUP BY ID_GROUP) T4 ON T4.ID_GROUP=T1.ID_GROUP 
                LEFT JOIN file_list T6 ON T6.ID_FILE=T2.PROFILE_IMAGE
                LEFT JOIN (
                    SELECT COUNT(T2.ID_MESSAGE) AS NEW_MESSAGE, T1.ID_GROUP FROM user_group T1
                    LEFT JOIN message T2 ON (T1.ID_GROUP=T2.ID_GROUP AND T2.CREATED>T1.LAST_VISIT)
                    WHERE T1.ID_USER=?
                    GROUP BY T1.ID_GROUP
                ) T5 ON T5.ID_GROUP=T1.ID_GROUP
                WHERE T1.ID_GROUP IN (" . implode(',', array_keys($userGroups)) . ")", $user->id)->fetchAll();
            foreach($groups as $group) {
                $groupModel = new Group();
                $user = new Entities\User();
                $user->surname = $group->TEACHER_SURNAME;
                $user->name = $group->TEACHER_NAME;
                $user->profileImage = "http://cdn.lato.cz/" . $group->PATH . "/" . $group->FILENAME;
                $groupModel->id = $group->ID_GROUP;
                $groupModel->name = $group->NAME;
                $groupModel->shortcut = $group->SHORTCUT;
                $groupModel->mainColor = $group->MAIN_COLOR;
                $groupModel->numberOfStudents = $group->STUDENTS;
                $groupModel->teacher = $user;
                $groupModel->newMessages = $group->NEW_MESSAGE;
                $return[] = $groupModel;
            }
        } 
        return $return;       
    }
    
 
      
    
}