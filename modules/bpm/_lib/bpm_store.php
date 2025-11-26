<?php
if (session_status() === PHP_SESSION_NONE) session_start();
class BpmStore {
  private $base;
  function __construct(){ $this->base = __DIR__ . '/../../storage/bpm'; if (!is_dir($this->base)) mkdir($this->base, 0777, true); }
  private function file($n){ return $this->base . '/' . $n . '.json'; }
  private function read($n){ $f=$this->file($n); return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : []; }
  private function write($n,$d){ file_put_contents($this->file($n), json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }
  function listProcesses($filters=[]){ $all=$this->read('processes'); return array_values(array_filter($all, function($p) use($filters){
    foreach($filters as $k=>$v){ if($v===''||$v===null)continue; if(isset($p[$k]) && stripos((string)$p[$k], (string)$v)===false) return false; } return true;
  }));}
  function saveProcess($proc){ $all=$this->read('processes'); if(empty($proc['id'])){ $proc['id']=uniqid('p_',true); $proc['version']=$proc['version']??1; $proc['status']=$proc['status']??'draft'; $proc['updated_at']=date('c'); $all[]=$proc; } else {
    foreach($all as &$p){ if($p['id']===$proc['id']){ $proc['updated_at']=date('c'); $p=array_merge($p,$proc); break; } } } $this->write('processes',$all); return $proc; }
  function getProcess($id){ foreach($this->read('processes') as $p){ if($p['id']===$id) return $p; } return null; }
  function deleteProcess($id){ $all=$this->read('processes'); $all=array_values(array_filter($all, fn($p)=>$p['id']!==$id)); $this->write('processes',$all); return true; }
}