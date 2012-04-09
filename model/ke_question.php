<?php

require_once 'core/ke_model.php';
require_once 'model/ke_user.php';
require_once 'model/ke_answer.php';
require_once 'model/ke_community.php';

class ke_question extends ke_model
{
   public $id;
   public $text;
   public $user_id;
   public $created;
   public $updated;
   public $num_answers;
   public $status;
   public $reward;
   public $user;

   public function __construct($q=FALSE)
   {
      parent::__construct('questions');
      if($q)
      {
         $this->id = $this->intval($q['id']);
         $this->text = $q['text'];
         $this->user_id = $this->intval($q['user_id']);
         $this->created = $q['created'];
         $this->updated = $q['updated'];
         $this->num_answers = intval($q['num_answers']);
         
         $this->status = intval($q['status']);
         if($this->status == 0 AND $this->num_answers > 0)
            $this->status = 1;
         
         $this->reward = intval($q['reward']);
         if( $this->is_solved() )
            $this->reward = 0;
         
         $this->user = new ke_user();
         $this->user = $this->user->get($this->user_id);
      }
      else
      {
         $this->id = NULL;
         $this->text = '';
         $this->user_id = NULL;
         $this->created = Date('j-n-Y H:i:s');
         $this->updated = Date('j-n-Y H:i:s');
         $this->num_answers = 0;
         $this->status = 0;
         $this->reward = 1;
         $this->user = FALSE;
      }
   }
   
   public function title()
   {
      if(strlen($this->text) > 80)
         return substr($this->text, 0, 80)."...";
      else
         return $this->text;
   }
   
   public function resume()
   {
      if(strlen($this->text) > 300)
         return substr($this->text, 0, 300)."...";
      else
         return $this->text;
   }
   
   public function text2html()
   {
      return $this->var2html($this->text);
   }
   
   public function created_timesince()
   {
      return $this->var2timesince($this->created);
   }
   
   public function updated_timesince()
   {
      return $this->var2timesince($this->updated);
   }
   
   public function set_text($t)
   {
      $this->text = $this->nohtml($t);
   }

   public function get_status($s=NULL)
   {
      if( !isset($s) )
         $s = $this->status;
      if($s == 0)
         return 'nueva';
      else if($s == 1)
         return 'abierta';
      else if($s == 2)
         return 'incompleta';
      else if($s == 9)
         return 'parcialmente solucionada';
      else if($s == 10)
         return "pendiente de confirmación";
      else if($s == 11)
         return 'solucionada';
      else if($s == 20)
         return 'duplicada';
      else if($s == 21)
         return 'erronea';
      else if($s == 22)
         return 'antigua';
      else
         return 'estado desconocido';
   }
   
   public function set_solved()
   {
      $this->updated = Date('j-n-Y H:i:s');
      $this->reward = 0;
      $this->status = 11;
      return $this->save();
   }

   public function is_solved()
   {
      return ($this->status >= 10);
   }
   
   public function num_solved()
   {
      $num = 0;
      $aux = $this->db->select("SELECT COUNT(*) as num FROM ".$this->table_name." WHERE status >= 10;");
      if($aux)
         $num = intval($aux[0]['num']);
      return $num;
   }
   
   public function status_stats()
   {
      $stats = array(
          0 => 0,
          1 => 0,
          2 => 0,
          3 => 0,
          4 => 0,
          5 => 0,
          6 => 0,
          7 => 0,
          8 => 0,
          9 => 0,
          10 => 0,
          11 => 0,
          12 => 0,
          13 => 0,
          14 => 0,
          15 => 0,
          16 => 0,
          17 => 0,
          18 => 0,
          19 => 0,
          20 => 0,
          21 => 0,
          22 => 0
      );
      $aux = $this->db->select("SELECT status, COUNT(*) as num FROM ".$this->table_name." GROUP BY status;");
      if($aux)
      {
         foreach($aux as $s)
         {
            if( intval($s['num']) > 0)
               $stats[ intval($s['status']) ] = intval($s['num']);
         }
      }
      return $stats;
   }
   
   public function url()
   {
      return KE_PATH."question/".$this->id;
   }
   
   public function is_readed()
   {
      if( isset($_COOKIE['q_'.$this->id]) )
         return ( strtotime($_COOKIE['q_'.$this->id]) >= strtotime($this->updated) );
      else
         return FALSE;
   }
   
   public function mark_as_readed()
   {
      setcookie('q_'.$this->id, Date('Y-m-d H:i:s'), time()+2592000, KE_PATH); /// expira en 30 dias
      /// una de cada 5 veces añadimos un punto a la recompensa
      if( !$this->is_solved() AND rand(0, 4) == 0 )
         $this->add_reward();
   }
   
   public function add_reward($p=1)
   {
      if( !$this->is_solved() )
      {
         $this->reward += intval($p);
         return $this->save();
      }
      else
         return FALSE;
   }
   
   public function get_answers()
   {
      $answer = new ke_answer();
      $answers = $answer->all_from_question($this->id);
      if( count($answers) != $this->num_answers)
      {
         $this->num_answers = count($answers);
         $this->updated = Date('j-n-Y H:i:s');
         if($this->num_answers > 0 AND $this->status == 0)
            $this->status = 1;
      }
      return $answers;
   }
   
   public function get_communities()
   {
      $comlist = array();
      $community = new ke_community();
      $cq = new ke_community_question();
      foreach($cq->all_from_question($this->id) as $cq2)
      {
         $comm2 = $community->get($cq2->community_id);
         if($comm2)
            $comlist[] = $comm2;
      }
      return $comlist;
   }
   
   public function search($query='')
   {
      $qlist = array();
      if($query != '')
      {
         $questions = $this->db->select_limit("SELECT * FROM ".$this->table_name." WHERE text LIKE '%".$query."%'");
         if($questions)
         {
            foreach($questions as $q)
            {
               $qlist[] = new ke_question($q);
            }
         }
      }
      return $qlist;
   }
   
   public function get($id)
   {
      if( isset($id) )
      {
         $q = $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = '".$id."';");
         if($q)
            return new ke_question($q[0]);
         else
            return FALSE;
      }
      else
         return FALSE;
   }
   
   public function exists()
   {
      if( is_null($this->id) )
         return FALSE;
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = '".$this->id."';");
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET text = ".$this->var2str($this->text).",
            user_id = ".$this->var2str($this->user_id).", created = ".$this->var2str($this->created).",
            updated = ".$this->var2str($this->updated).", num_answers = ".$this->var2str($this->num_answers).",
            status = ".$this->var2str($this->status).", reward = ".$this->var2str($this->reward)."
            WHERE id = '".$this->id."';";
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (text,user_id,created,updated,num_answers,status,reward) VALUES
            (".$this->var2str($this->text).",".$this->var2str($this->user_id).",".$this->var2str($this->created).",
            ".$this->var2str($this->updated).",".$this->var2str($this->num_answers).",".$this->var2str($this->status).",
            ".$this->var2str($this->reward).");";
         if( $this->db->exec($sql) )
         {
            $id = $this->db->select("SELECT LAST_INSERT_ID() as id;");
            if($id)
            {
               $this->id = intval($id[0]['id']);
               return TRUE;
            }
            else
               return FALSE;
         }
         else
            return FALSE;
      }
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE id = '".$this->id."';");
   }
   
   public function all($offset=0, $limit=KE_ITEM_LIMIT)
   {
      $qlist = array();
      $questions = $this->db->select_limit("SELECT * FROM ".$this->table_name." ORDER BY updated DESC", $offset, $limit);
      if($questions)
      {
         foreach($questions as $q)
            $qlist[] = new ke_question($q);
      }
      return $qlist;
   }
   
   public function all_from_user($uid, $offset=0, $limit=KE_ITEM_LIMIT)
   {
      $qlist = array();
      $questions = $this->db->select_limit("SELECT DISTINCT * FROM ".$this->table_name." WHERE user_id = '".$uid."'
         OR id IN (SELECT question_id FROM answers WHERE user_id = '".$uid."') ORDER BY updated DESC", $offset, $limit);
      if($questions)
      {
         foreach($questions as $q)
            $qlist[] = new ke_question($q);
      }
      return $qlist;
   }
   
   public function all_unreaded($offset=0, $limit=KE_ITEM_LIMIT)
   {
      $qlist = array();
      $questions = $this->db->select_limit("SELECT * FROM ".$this->table_name." WHERE status < 10 ORDER BY updated ASC", $offset, $limit);
      if($questions)
      {
         foreach($questions as $q)
            $qlist[] = new ke_question($q);
      }
      return $qlist;
   }
   
   public function avg_reward()
   {
      $reward = 0;
      $aux = $this->db->select("SELECT AVG(reward) as reward FROM ".$this->table_name.";");
      if($aux)
         $reward = floatval($aux[0]['reward']);
      return $reward;
   }
}

?>