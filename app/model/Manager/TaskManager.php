<?php
namespace App\Model\Manager;

use App\Model\Entities\Task;
use App\Model\Entities\TaskCommit;

class TaskManager extends BaseManager 
{     
    
    public function createTask(Task $task)
    {
        $this->db->begin();
        $idTask = $this->db->fetchSingle("SELECT id FROM task WHERE message_id=?", $task->idMessage); 
        if($idTask) {
            $this->db->query("UPDATE task SET ", [
                'name' => $task->title,
                'deadline' => $task->deadline,
                'online' => $task->online,
                'create_classification' => $task->create_classification
            ], "WHERE id=?", $idTask);    
        } else {
            $this->db->query("INSERT INTO task", [
                'name' => $task->title,
                'message_id' => $task->idMessage,
                'deadline' => $task->deadline,
                'online' => $task->online,
                'create_classification' => $task->create_classification
            ]);
            $idTask = $this->db->getInsertId();
        }      
        $this->db->commit();
        
        return $idTask;
    }
    
    public function getTaskStats($groups, \App\Model\Entities\User $user)
    {
        $ownerIds = [-1];
        $studentIds = [-1];
        foreach($groups as $group) {
            if($group->relation === 'owner') {
                $ownerIds[] = $group->id;
            } else {
                $studentIds[] = $group->id;
            }
        }
        return $this->db->fetch("
                SELECT 
                    SUM(IF(T2.id IS NULL, 0, 1)) AS tasks,
                    SUM(IF(T3.id IS NULL, 0, 1)) AS owner
                FROM task T1 
                LEFT JOIN message T2 ON (T1.message_id = T2.id AND T2.deleted=0 AND T2.group_id IN (" . implode(",", $studentIds) . "))
                LEFT JOIN message T3 ON (T1.message_id = T3.id AND T3.deleted=0 AND T3.group_id IN (" . implode(",", $ownerIds) . "))");
    }
    
    public function getClosestTask($groups, $active, $user, $fromGroupOwner = false) 
    {
        if(!empty($groups)) {
            $now = new \DateTime();         
            $tasksArray = [];
            
            if($fromGroupOwner) {
                $groupIds = [];
                foreach($groups as $group) {
                    if($group->relation === 'owner') {
                        $groupIds[] = $group->id;
                    }
                }
                if($groupIds) {
                    $tasks = $this->db->fetchAll("SELECT 
                            T1.id,
                            T1.name,
                            T1.message_id,
                            T1.deadline,
                            T2.text,
                            T2.group_id,
                            T2.id AS message_id,
                            T3.id AS user_id,
                            T3.name AS user_name,
                            T3.surname AS user_surname,
                            T7.profile_image,
                            T3.sex AS user_sex,
                            T4.commit_count,
                            T5.id AS commit_id,
                            T5.created_when AS commit_created,
                            T5.updated_when AS commit_updated,
                            T6.task_users,
                            T1.online,
                            T1.create_classification,
                            T2.user_id as created_by,
                            T8.id AS class_group_id
                    FROM message T2 
                    JOIN task T1 ON (T1.message_id = T2.id)
                    JOIN user T3 ON (T2.user_id=T3.id)
                    JOIN user_real T7 ON (T3.id=T7.id)
                    LEFT JOIN (SELECT COUNT(id) AS commit_count, task_id FROM task_commit GROUP BY task_id) T4 ON T4.task_id=T1.id
                    LEFT JOIN task_commit T5 ON T1.id=T5.task_id AND T5.user_id=?
                    LEFT JOIN ( SELECT COUNT(*) AS task_users, M.id FROM message M JOIN group_user G ON M.group_id=G.group_id GROUP BY M.id) T6 ON T6.id = T2.id
                    LEFT JOIN classification_group T8 ON T1.id = T8.task_id
                    WHERE T2.group_id IN (" . implode(",", $groupIds) . ") AND T2.deleted=0 AND T1.closed=?
                    ORDER BY T2.created_when DESC", $user->id , $active ? 0 : 1);
                } else {
                    $tasks = [];
                }                
            } else {
                $groupIds = [];
                foreach($groups as $group) {
                    if($group->relation === 'student') {
                        $groupIds[] = $group->id;
                    }
                }
                if(!empty($groupIds)) {
                    $active ? $timeCompare = '>=' : $timeCompare = '<';
                    $tasks = $this->db->fetchAll("SELECT 
                                T1.id,
                                T1.name,
                                T1.message_id,
                                T1.deadline,
                                T2.text,
                                T2.group_id,
                                T3.id AS user_id,
                                T3.name AS user_name,
                                T3.surname AS user_surname,
                                T7.profile_image,
                                T3.sex AS user_sex,
                                T4.commit_count,
                                T5.id AS commit_id,
                                T5.created_when AS commit_created,
                                T5.updated_when AS commit_updated,
                                T6.task_users,
                                T1.online,
                                T1.create_classification,
                                T2.user_id as created_by,
                                T8.id AS class_group_id,
                                T9.grade
                        FROM task T1 
                        JOIN message T2 ON (T1.message_id = T2.id AND T2.deleted=0)
                        JOIN user T3 ON (T2.user_id=T3.id)
                        JOIN user_real T7 ON (T3.id=T7.id)
                        LEFT JOIN (SELECT COUNT(id) AS commit_count, task_id FROM task_commit GROUP BY task_id) T4 ON T4.task_id=T1.id
                        LEFT JOIN task_commit T5 ON T1.id=T5.task_id AND T5.user_id=?
                        LEFT JOIN ( SELECT COUNT(*) AS task_users, M.id FROM message M JOIN group_user G ON M.group_id=G.group_id GROUP BY M.id) T6 ON T6.id = T2.id
                        LEFT JOIN classification_group T8 ON T1.id = T8.task_id
                        LEFT JOIN classification T9 ON T9.classification_group_id = T8.id AND T9.user_id=?
                        WHERE T2.group_id IN (" . implode(",", $groupIds) . ") AND T1.deadline" . $timeCompare . "NOW()
                        ORDER BY T1.deadline ASC", $user->id, $user->id);
                } else {
                     $tasks = [];
                }
            }
            
            foreach($tasks as $task) {
                $taskObject  = new Task();
                $taskObject->message = new \App\Model\Entities\Message();
                $taskObject->message->text = $task->text;
                $taskObject->message->id = $task->message_id;
                $taskObject->message->user = new \App\Model\Entities\User();
                $taskObject->message->user->id = $task->user_id;
                $taskObject->message->user->name = $task->user_name;
                $taskObject->message->user->surname = $task->user_surname;
                $taskObject->message->user->profileImage = \App\Model\Entities\User::createProfilePath($task->profile_image, $task->user_sex);
        
                $taskObject->commitCount = $task->commit_count;
                $taskObject->isCreator = $user->id == $task->created_by;
                $taskObject->deadline = $task->deadline;
                $taskObject->title = $task->name;
                $taskObject->idMessage = $task->message_id;
                $taskObject->group = $groups[$task->group_id];
                $taskObject->timeLeft = $now->diff($task->deadline);
                $taskObject->taskMembers = $task->task_users;
                $taskObject->online = $task->online;
                $taskObject->id = $task->id;
                $taskObject->createClassification = $task->create_classification;
                $taskObject->idClassificationGroup = $task->class_group_id;
                        
                if(!empty($task->commit_id)) {
                    $taskObject->commit = new \App\Model\Entities\TaskCommit();
                    $taskObject->commit->idCommit = $task->commit_id;
                    $taskObject->commit->created = $task->commit_created;
                    $taskObject->commit->updated = $task->commit_updated;
                    $taskObject->commit->grade = $task->grade;
                }
                $tasksArray[$task->id] = $taskObject;
            }
            return $tasksArray;
        } else {
            return [];
        }
    }
    
    public function getTask($idTask, $user) 
    {
        $now = new \DateTime();
        $task = $this->db->fetch("SELECT 
                        T1.id,
                        T1.name,
                        T1.message_id,
                        T1.deadline,
                        T2.id AS class_group_id,
                        T3.group_id,
                        T4.slug,
                        T4.name AS group_name,
                        T5.task_users,
                        T6.id AS commit_id,
                        T6.created_when AS commit_created,
                        T6.updated_when AS commit_updated,
                        T6.comment AS commit_comment,
                        T3.text,
                        T3.user_id as created_by,
                        T1.create_classification
                FROM task T1 
                LEFT JOIN classification_group T2 ON T1.id = T2.task_id
                LEFT JOIN message T3 ON T3.id=T1.message_id
                LEFT JOIN `group` T4 ON T3.group_id = T4.id
                LEFT JOIN ( SELECT COUNT(*) AS task_users, M.id FROM message M JOIN group_user G ON M.id=G.group_id GROUP BY M.id) T5 ON T5.id = T3.id
                LEFT JOIN task_commit T6 ON (T1.id=T6.task_id AND T6.user_id=?)
                WHERE T1.id = ?", $user->id, $idTask);
        $taskObject  = new Task();
        $taskObject->id = $task->id;
        $taskObject->deadline = $task->deadline;
        $taskObject->title = $task->name;
        $taskObject->idMessage = $task->message_id;
        $taskObject->timeLeft = $now->diff($task->deadline);
        $taskObject->idClassificationGroup = $task->class_group_id;
        $taskObject->message = new \App\Model\Entities\Message();
        $taskObject->message->group = new \App\Model\Entities\Group();
        $taskObject->message->group->id = $task->group_id;
        $taskObject->message->group->urlId = $task->slug;
        $taskObject->message->text = $task->text;
        $taskObject->taskMembers = $task->task_users;
        $taskObject->isCreator = $user->id == $task->created_by;
        $taskObject->createClassification = $task->create_classification;
        $taskObject->group = new \App\Model\Entities\Group();
        $taskObject->group->name = $task->group_name;
        $taskObject->group->slug = $task->slug;
        if(!empty($task->commit_id)) {
            $commit = new \App\Model\Entities\TaskCommit();
            $commit->idCommit = $task->commit_id;
            $commit->created = $task->commit_created;
            $commit->updated = $task->commit_updated;
            $commit->comment = $task->commit_comment;
            $commit->files = [];
            $files = $this->db->fetchAll("SELECT T1.id, T3.id AS file_id, T3.full_path, T3.type, T3.filename
                        FROM task_commit T1
                        LEFT JOIN task_commit_attachment T2 ON T1.id = T2.commit_id
                        LEFT JOIN file_list T3 ON T2.file_id = T3.id
                        WHERE T1.id = ?", $commit->idCommit);
            foreach($files as $file) {
                $commit->files[] =  (object)['fileId' => $file->file_id, 'path' => $file->full_path, 'filename' => $file->filename, 'type' => $file->type];
            }
            $taskObject->commit = $commit;
        }
        return $taskObject;
    }
    
    public function getCommitByUser($idTask, $idUser) 
    {              
        $return = new TaskCommit();
        $commit = $this->db->fetchAll("SELECT T1.id, T1.comment, T2.file_id,
                            T3.path,
                            T3.filename,
                            T1.created_when,
                            T1.updated_when,
                            T3.mime,
                            T3.extension,
                            T3.type,
                            T3.created_when AS file_created,
                            IFNULL(T3.name, T3.filename) as name
                        FROM task_commit T1
                        LEFT JOIN task_commit_attachment T2 ON T1.id=T2.commit_id
                        LEFT JOIN file_list T3 ON T2.file_id=T3.id
                        WHERE T1.task_id = ? AND T1.user_id = ?", $idTask, $idUser);
        if(!$commit) {
            return null;
        }
        
        foreach($commit as $attach) {
            $return->idCommit = $attach->id;
            $return->comment = $attach->comment;
            $return->created = $attach->created_when;
            $return->updated = $attach->updated_when;
            if(!empty($attach->file_id)) {
                $return->files[] = (object)[
                    'idFile' => $attach->file_id, 
                    'created' => $attach->file_created, 
                    'mime' => $attach->mime,
                    'extension' => $attach->extension, 
                    'type' => $attach->type, 
                    'path' => 'https://cdn.lato.cz/' . $attach->path . '/' . $attach->filename, 
                    'filename' => $attach->filename,                    
                    'name' => $attach->name
                ];
            }
        }
        return $return;
    }
    
    public function isUserCommit($idCommit, \App\Model\Entities\User $user)
    {
        $id = $this->db->fetchSingle("SELECT id FROM task_commit WHERE id=? AND user_id=?", $idCommit, $user->id);
        return $idCommit == $id;
    }
    
    public function createTaskCommit(TaskCommit $taskCommit, $attachments)
    {
        $this->db->begin();
        if(empty($taskCommit->idCommit)) {
            $this->db->query("INSERT INTO task_commit", [
                'task_id' => $taskCommit->idTask,
                'user_id' => $taskCommit->user->id,
                'created_by' => $this->user->id,
                'comment' => $taskCommit->comment
            ]);
            $taskCommit->idCommit = $this->db->getInsertId();
        } else {
            $this->db->query("UPDATE task_commit SET", [
                'comment' => $taskCommit->comment,
                'updated_when' => new \DateTime()
            ], "WHERE id=?", $taskCommit->idCommit);
        }
        
        if(!empty($attachments)) {
            foreach($attachments as $idAttach) {
                $this->addAttachment($idAttach, $taskCommit->idCommit);
            }
        }
        
        $this->db->commit();
        return $taskCommit->idCommit;
    }
    
    
    public function addAttachment($idFile, $idCommit = null)
    {
        if(!empty($idFile)) {
            $this->db->query("INSERT INTO task_commit_attachment", [
                'commit_id' => $idCommit,
                'file_id' => $idFile
            ]);
        }
    }
    
    public function removeAttachment($idFile)
    {
        if(!empty($idFile)) {     
            $this->db->query("DELETE FROM task_commit_attachment WHERE file_id=?", $idFile);
        }
    }
}
