<?php
declare(strict_types=1);

/*
 * AMHEEX - Gerenciador de Arquivos
 * - Upload múltiplo de arquivos
 * - Upload de ZIP com confirmação para extrair
 * - Download de pastas como ZIP
 * - Botão "Zipar" em arquivos/pastas
 * - Visualização de imagens e vídeos
 * - Editor somente para arquivos de texto/código
 * - Diálogos flutuantes com borda RGB ESTÁTICA (sem animação)
 */

@ini_set('upload_max_filesize', '16000M');
@ini_set('post_max_size', '16000M');
@ini_set('memory_limit', '16000M');
@ini_set('max_execution_time', '86400');
@ini_set('max_input_time', '86400');
@ini_set('max_file_uploads', '1000');
ignore_user_abort(true);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$BASE = realpath(__DIR__);
if ($BASE === false) die('Pasta base indisponível.');

function clean_rel(string $path): string {
    $path = str_replace(["\0", '\\'], ['', '/'], $path);
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') { array_pop($parts); continue; }
        $parts[] = $part;
    }
    return implode('/', $parts);
}
function abs_path(string $rel): string {
    global $BASE;
    $rel = clean_rel($rel);
    return $BASE . ($rel !== '' ? DIRECTORY_SEPARATOR . $rel : '');
}
function inside_base(string $path): bool {
    global $BASE;
    $real = realpath($path);
    if ($real === false) return false;
    return $real === $BASE || str_starts_with($real, $BASE . DIRECTORY_SEPARATOR);
}
function mime_of(string $file): string {
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($file);
        if ($m) return $m;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $map = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
        'webp'=>'image/webp','svg'=>'image/svg+xml','bmp'=>'image/bmp','ico'=>'image/x-icon',
        'mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg','mov'=>'video/quicktime',
        'm4v'=>'video/x-m4v','avi'=>'video/x-msvideo','mkv'=>'video/x-matroska',
        'txt'=>'text/plain','html'=>'text/html','htm'=>'text/html','css'=>'text/css',
        'js'=>'text/javascript','json'=>'application/json','xml'=>'application/xml',
        'php'=>'text/plain','py'=>'text/plain','java'=>'text/plain','c'=>'text/plain',
        'cpp'=>'text/plain','h'=>'text/plain','md'=>'text/markdown','csv'=>'text/csv'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
function is_media(string $file): bool {
    return str_starts_with(mime_of($file), 'image/') || str_starts_with(mime_of($file), 'video/');
}
function is_editable(string $file): bool {
    $mime = mime_of($file);
    if (str_starts_with($mime, 'text/')) return true;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, ['php','json','js','css','html','htm','xml','md','csv','py','java','c','cpp','h','hpp','sql','sh','ini','conf','log'], true);
}
function json_out(array $data): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function safe_filename(string $name): string {
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?: 'arquivo';
    return trim($name, " .") ?: 'arquivo';
}
function unique_target(string $dir, string $name): string {
    $name = safe_filename($name);
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!file_exists($target)) return $target;
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $stem = pathinfo($name, PATHINFO_FILENAME);
    for ($i=1; $i<100000; $i++) {
        $n = $stem . ' (' . $i . ')' . ($ext ? '.' . $ext : '');
        $target = $dir . DIRECTORY_SEPARATOR . $n;
        if (!file_exists($target)) return $target;
    }
    return $dir . DIRECTORY_SEPARATOR . uniqid('', true) . '_' . $name;
}
function zip_path(string $source, string $destination): bool {
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    $source = realpath($source);
    if ($source === false) { $zip->close(); return false; }

    if (is_dir($source)) {
        $root = basename($source);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $full = $item->getPathname();
            $rel = substr($full, strlen($source) + 1);
            $local = $root . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            if ($item->isDir()) $zip->addEmptyDir($local);
            else $zip->addFile($full, $local);
        }
        if ($it->getInnerIterator()->valid() === false) $zip->addEmptyDir($root);
    } else {
        $zip->addFile($source, basename($source));
    }
    return $zip->close();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list') {
    $rel = clean_rel((string)($_GET['path'] ?? $_POST['path'] ?? ''));
    $dir = abs_path($rel);
    if (!is_dir($dir) || !inside_base($dir)) json_out(['ok'=>false,'error'=>'Pasta inválida.']);
    $items = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $isDir = is_dir($full);
        $items[] = [
            'name'=>$name,
            'path'=>($rel ? $rel.'/' : '').$name,
            'type'=>$isDir?'folder':'file',
            'size'=>$isDir?0:(int)@filesize($full),
            'mime'=>$isDir?'directory':mime_of($full),
            'editable'=>!$isDir && is_editable($full),
            'media'=>!$isDir && is_media($full),
            'mtime'=>(int)@filemtime($full)
        ];
    }
    usort($items, fn($a,$b)=>($a['type']!==$b['type']) ? ($a['type']==='folder'?-1:1) : strnatcasecmp($a['name'],$b['name']));
    json_out(['ok'=>true,'path'=>$rel,'items'=>$items]);
}

if ($action === 'mkdir') {
    $dir = abs_path(clean_rel((string)($_POST['path'] ?? '')));
    if (!is_dir($dir) || !inside_base($dir)) json_out(['ok'=>false,'error'=>'Pasta inválida.']);
    $name = safe_filename((string)($_POST['name'] ?? ''));
    if ($name === '') json_out(['ok'=>false,'error'=>'Nome inválido.']);
    $target = unique_target($dir, $name);
    json_out(['ok'=>@mkdir($target,0775,false),'error'=>'Não foi possível criar a pasta.']);
}

if ($action === 'new_file') {
    $dir = abs_path(clean_rel((string)($_POST['path'] ?? '')));
    if (!is_dir($dir) || !inside_base($dir)) json_out(['ok'=>false,'error'=>'Pasta inválida.']);
    $name = safe_filename((string)($_POST['name'] ?? 'arquivo.txt'));
    $target = unique_target($dir,$name);
    $ok = @file_put_contents($target, (string)($_POST['content'] ?? '')) !== false;
    json_out(['ok'=>$ok,'error'=>'Não foi possível criar o arquivo.']);
}

if ($action === 'rename') {
    $old = abs_path(clean_rel((string)($_POST['path'] ?? '')));
    if (!file_exists($old) || !inside_base($old)) json_out(['ok'=>false,'error'=>'Arquivo inválido.']);
    $name = safe_filename((string)($_POST['name'] ?? ''));
    $target = dirname($old).DIRECTORY_SEPARATOR.$name;
    if ($target !== $old && file_exists($target)) json_out(['ok'=>false,'error'=>'Já existe um item com esse nome.']);
    json_out(['ok'=>@rename($old,$target),'error'=>'Não foi possível renomear.']);
}

if ($action === 'delete') {
    $target = abs_path(clean_rel((string)($_POST['path'] ?? '')));
    if (!file_exists($target) || $target === $BASE || !inside_base($target)) json_out(['ok'=>false,'error'=>'Item inválido.']);
    $ok = is_dir($target) ? @rmdir($target) : @unlink($target);
    json_out(['ok'=>$ok,'error'=>'Pasta não vazia ou não foi possível excluir.']);
}

if ($action === 'save') {
    $target = abs_path(clean_rel((string)($_POST['path'] ?? '')));
    if (!is_file($target) || !inside_base($target) || !is_editable($target)) json_out(['ok'=>false,'error'=>'Arquivo não editável.']);
    $ok = @file_put_contents($target, (string)($_POST['content'] ?? '')) !== false;
    json_out(['ok'=>$ok,'error'=>'Não foi possível salvar.']);
}

if ($action === 'upload') {
    $rel = clean_rel((string)($_POST['path'] ?? ''));
    $dir = abs_path($rel);
    if (!is_dir($dir) || !inside_base($dir)) json_out(['ok'=>false,'error'=>'Pasta inválida.']);
    $extractZip = filter_var($_POST['extract_zip'] ?? '0', FILTER_VALIDATE_BOOLEAN);
    $uploaded = [];
    $errors = [];
    if (!isset($_FILES['files'])) json_out(['ok'=>false,'error'=>'Nenhum arquivo enviado.']);

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    for ($i=0; $i<$count; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        if ($err !== UPLOAD_ERR_OK) { $errors[] = $name . ' (erro ' . $err . ')'; continue; }
        $safe = safe_filename((string)$name);
        $target = unique_target($dir,$safe);
        if (!@move_uploaded_file($tmp,$target)) { $errors[]=$name; continue; }
        $uploaded[]=$safe;

        if ($extractZip && strtolower(pathinfo($safe,PATHINFO_EXTENSION)) === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($target) === true) {
                $extractDir = unique_target($dir, pathinfo($safe,PATHINFO_FILENAME));
                @mkdir($extractDir,0775,true);
                $zip->extractTo($extractDir);
                $zip->close();
            } else {
                $errors[] = $safe . ' (ZIP inválido)';
            }
        }
    }
    json_out(['ok'=>count($uploaded)>0,'uploaded'=>$uploaded,'errors'=>$errors]);
}

if ($action === 'zip') {
    $rel = clean_rel((string)($_POST['path'] ?? ''));
    $source = abs_path($rel);
    if (!file_exists($source) || !inside_base($source)) json_out(['ok'=>false,'error'=>'Item inválido.']);
    $name = safe_filename((string)($_POST['name'] ?? (basename($source).'.zip')));
    if (strtolower(pathinfo($name,PATHINFO_EXTENSION)) !== 'zip') $name .= '.zip';
    $dest = unique_target(dirname($source),$name);
    $ok = zip_path($source,$dest);
    json_out(['ok'=>$ok,'file'=>basename($dest),'error'=>$ok?'':'A extensão ZIP do PHP não está disponível ou não foi possível compactar.']);
}

if ($action === 'read') {
    $rel = clean_rel((string)($_GET['path'] ?? ''));
    $file = abs_path($rel);
    if (!is_file($file) || !inside_base($file)) json_out(['ok'=>false,'error'=>'Arquivo inválido.']);
    $mime = mime_of($file);
    if (str_starts_with($mime,'image/') || str_starts_with($mime,'video/')) {
        json_out(['ok'=>true,'media'=>true,'mime'=>$mime,'url'=>'?action=media&path='.rawurlencode($rel)]);
    }
    if (!is_editable($file)) json_out(['ok'=>false,'error'=>'Este tipo de arquivo não possui editor de conteúdo.']);
    $content = @file_get_contents($file);
    json_out(['ok'=>true,'media'=>false,'content'=>$content===false?'':$content,'mime'=>$mime]);
}

if ($action === 'media') {
    $rel = clean_rel((string)($_GET['path'] ?? ''));
    $file = abs_path($rel);
    if (!is_file($file) || !inside_base($file) || !is_media($file)) { http_response_code(404); exit; }
    $mime = mime_of($file);
    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($file));
    header('Content-Disposition: inline; filename="'.str_replace('"','',basename($file)).'"');
    readfile($file);
    exit;
}

if ($action === 'download') {
    $rel = clean_rel((string)($_GET['path'] ?? ''));
    $target = abs_path($rel);
    if (!file_exists($target) || !inside_base($target)) { http_response_code(404); exit('Arquivo inválido.'); }
    if (is_dir($target)) {
        $tmp = tempnam(sys_get_temp_dir(),'amheex_').'.zip';
        if (!zip_path($target,$tmp)) { http_response_code(500); exit('Não foi possível criar o ZIP.'); }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($target).'.zip"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.str_replace('"','',basename($target)).'"');
    header('Content-Length: '.filesize($target));
    readfile($target);
    exit;
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AMHEEX — Gerenciador de Arquivos</title>
<style>
:root{
 --bg:#000033; /* Azul marinho escuro de fundo */
 --panel:#11151c;--panel2:#171c25;--text:#f5f7fa;--muted:#9da7b5;
 --rgb:linear-gradient(90deg,#ff0000 0%,#ffff00 20%,#00ff00 40%,#00ffff 60%,#0000ff 80%,#ff00ff 100%);
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text)}
button,input,textarea{font:inherit}
button{cursor:pointer}
.app{width:100%;max-width:1400px;margin:auto;padding:18px}
.topbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.brand{font-weight:800;font-size:21px;margin-right:auto}
.path{color:var(--muted);font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:45vw}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn,.icon-btn{background:var(--panel);color:var(--text);border:1px solid transparent;border-image:var(--rgb) 1;border-radius:10px;padding:10px 13px}
.btn:hover,.icon-btn:hover{background:var(--panel2)}
.btn svg,.icon-btn svg,.btn img,.icon-btn img,.item-icon{width:20px;height:20px;object-fit:contain;vertical-align:middle;display:inline-block}
.btn span{margin-left:6px}
.toolbar{border:1px solid transparent;border-image:var(--rgb) 1;background:var(--panel);padding:12px;border-radius:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.list{margin-top:12px;display:grid;gap:8px}
.item{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;background:var(--panel);border:1px solid transparent;border-image:var(--rgb) 1;border-radius:12px;padding:10px 12px}
.item:hover{background:var(--panel2)}
.item-main{min-width:0}
.item-name{font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.meta{font-size:12px;color:var(--muted);margin-top:3px}
.item-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.empty{padding:45px;text-align:center;color:var(--muted)}
.dialog-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.68);backdrop-filter:blur(7px);display:none;align-items:center;justify-content:center;padding:18px;z-index:1000}
.dialog-backdrop.open{display:flex}
.dialog{width:min(850px,100%);max-height:calc(100vh - 36px);overflow:auto;background:#10141b;border:2px solid transparent;border-image:var(--rgb) 1;border-radius:16px;box-shadow:0 20px 80px rgba(0,0,0,.55);padding:18px}
.dialog h2{margin:0 0 14px;font-size:20px}
.dialog-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}
.field{display:grid;gap:7px;margin:12px 0}
.field label{font-size:13px;color:#c6ced9}
.field input,.field textarea{width:100%;background:#090c11;color:#fff;border:1px solid transparent;border-image:var(--rgb) 1;border-radius:10px;padding:11px}
.field textarea{min-height:350px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}
.drop{border:1px dashed;border-image:var(--rgb) 1;border-radius:12px;padding:25px;text-align:center;color:var(--muted)}
.drop.drag{border-color:#fff}
.progress{height:7px;background:#252b35;border-radius:10px;overflow:hidden;margin-top:12px;display:none}
.progress>div{height:100%;width:0;background:var(--rgb)}
.preview{display:flex;align-items:center;justify-content:center;min-height:250px;background:#07090d;border-radius:12px;padding:10px}
.preview img,.preview video{max-width:100%;max-height:65vh;border-radius:8px}
.preview video{width:100%}
.notice{font-size:13px;color:var(--muted);line-height:1.5}
@media(max-width:700px){.item{grid-template-columns:auto 1fr}.item-actions{grid-column:1/-1;justify-content:flex-start}.path{max-width:100%}.toolbar{align-items:stretch}.toolbar>*{flex:1}}
</style>
</head>
<body>
<div class="app">
  <div class="topbar">
    <div class="brand">AMHEEX — Gerenciador</div>
    <div class="path" id="pathLabel">/</div>
    <button class="btn" id="upBtn">⬆ <span>Voltar</span></button>
  </div>

  <div class="toolbar">
    <button class="btn" id="newFileBtn">＋ <span>Novo arquivo</span></button>
    <button class="btn" id="newFolderBtn">📁 <span>Nova pasta</span></button>
    <button class="btn" id="uploadBtn">⇧ <span>Upload</span></button>
    <input id="fileInput" type="file" multiple hidden>
    <button class="btn" id="refreshBtn">↻ <span>Atualizar</span></button>
  </div>

  <div class="list" id="list"></div>
</div>

<div class="dialog-backdrop" id="uploadDialog">
 <div class="dialog">
  <h2>Upload de arquivos</h2>
  <div class="drop" id="dropArea">Selecione <b>quantos arquivos quiser</b> ou arraste vários arquivos para esta área.</div>
  <div id="selectedFiles" class="notice" style="margin-top:12px"></div>
  <div class="progress" id="progress"><div></div></div>
  <div class="dialog-footer">
   <button class="btn" data-close="uploadDialog">Cancelar</button>
   <button class="btn" id="sendUploadBtn">Enviar</button>
  </div>
 </div>
</div>

<div class="dialog-backdrop" id="zipDialog">
 <div class="dialog">
  <h2>ZIP detectado</h2>
  <div class="notice">O arquivo ZIP foi identificado. Deseja fazer o upload <b>já extraindo o conteúdo</b>?</div>
  <div class="dialog-footer">
   <button class="btn" id="zipNormalBtn">Enviar ZIP normal</button>
   <button class="btn" id="zipExtractBtn">Enviar e extrair</button>
  </div>
 </div>
</div>

<div class="dialog-backdrop" id="formDialog">
 <div class="dialog">
  <h2 id="formTitle"></h2>
  <div class="field"><label id="formLabel">Nome</label><input id="formInput" autocomplete="off"></div>
  <div class="dialog-footer"><button class="btn" data-close="formDialog">Cancelar</button><button class="btn" id="formOk">OK</button></div>
 </div>
</div>

<div class="dialog-backdrop" id="editorDialog">
 <div class="dialog">
  <h2 id="editorTitle">Editor de conteúdo</h2>
  <div class="field"><textarea id="editor"></textarea></div>
  <div class="dialog-footer"><button class="btn" data-close="editorDialog">Fechar</button><button class="btn" id="saveBtn">Salvar</button></div>
 </div>
</div>

<div class="dialog-backdrop" id="previewDialog">
 <div class="dialog">
  <h2 id="previewTitle">Visualização</h2>
  <div class="preview" id="preview"></div>
  <div class="dialog-footer"><button class="btn" data-close="previewDialog">Fechar</button></div>
 </div>
</div>

<script>
const API = location.pathname;
let currentPath = '';
let pendingFiles = [];
let zipPending = null;
let formCallback = null;
let editorPath = null;

const $ = s => document.querySelector(s);
const esc = s => String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const fmt = n => {if(!n)return '0 B';let u=['B','KB','MB','GB','TB'],i=Math.floor(Math.log(n)/Math.log(1024));return (n/Math.pow(1024,i)).toFixed(i?1:0)+' '+u[i]};
const ext = n => n.split('.').pop().toLowerCase();
const isImage = m => m && m.startsWith('image/');
const isVideo = m => m && m.startsWith('video/');
const icon = item => item.type==='folder'?'📁':isImage(item.mime)?'🖼️':isVideo(item.mime)?'🎬':ext(item.name)==='zip'?'🗜️':'📄';

function openDialog(id){$('#'+id).classList.add('open')}
function closeDialog(id){$('#'+id).classList.remove('open')}
document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>closeDialog(b.dataset.close));
document.querySelectorAll('.dialog-backdrop').forEach(b=>b.addEventListener('click',e=>{if(e.target===b)closeDialog(b.id)}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.dialog-backdrop.open').forEach(x=>x.classList.remove('open'))});

async function api(url, opts={}){
 const r=await fetch(url,opts); 
 const t=await r.text();
 try{return JSON.parse(t)}catch{return {ok:false,error:t||'Resposta inválida'}};
}

async function load(){
 const d=await api(API+'?action=list&path='+encodeURIComponent(currentPath));
 if(!d.ok){alert(d.error);return}
 currentPath=d.path||'';
 $('#pathLabel').textContent='/'+currentPath;
 $('#list').innerHTML='';
 if(currentPath){
   const up=document.createElement('div');up.className='item';
   up.innerHTML='<div>⬆</div><div class="item-main"><div class="item-name">..</div><div class="meta">Pasta anterior</div></div><div></div>';
   up.onclick=()=>{currentPath=currentPath.split('/').slice(0,-1).join('/');load()};$('#list').appendChild(up);
 }
 if(!d.items.length){$('#list').insertAdjacentHTML('beforeend','<div class="empty">Nenhum arquivo nesta pasta.</div>');return}
 d.items.forEach(item=>{
  const row=document.createElement('div');row.className='item';
  const mediaLabel=item.media?(isImage(item.mime)?'Imagem':'Vídeo'):'';
  row.innerHTML=`<div class="item-icon">${icon(item)}</div>
   <div class="item-main"><div class="item-name">${esc(item.name)}</div><div class="meta">${item.type==='folder'?'Pasta':fmt(item.size)+' · '+esc(mediaLabel||item.mime||'arquivo')}</div></div>
   <div class="item-actions">
    <button class="icon-btn open-item" title="${item.type==='folder'?'Abrir':'Visualizar/editar'}">${item.type==='folder'?'↗':(item.media?'👁':'✎')}</button>
    <button class="icon-btn rename" title="Renomear">✎</button>
    <button class="icon-btn zip" title="Zipar">🗜️</button>
    <button class="icon-btn download" title="Baixar${item.type==='folder'?' como ZIP':''}">⇩</button>
    <button class="icon-btn delete" title="Excluir">🗑</button>
   </div>`;
  row.querySelector('.open-item').onclick=e=>{e.stopPropagation();openItem(item)};
  row.querySelector('.rename').onclick=e=>{e.stopPropagation();askForm('Renomear','Novo nome',item.name,v=>rename(item.path,v))};
  row.querySelector('.zip').onclick=e=>{e.stopPropagation();zipItem(item.path,item.name)};
  row.querySelector('.download').onclick=e=>{e.stopPropagation();location.href=API+'?action=download&path='+encodeURIComponent(item.path)};
  row.querySelector('.delete').onclick=e=>{e.stopPropagation();askForm('Excluir item','Digite EXCLUIR para confirmar','',v=>{if(v==='EXCLUIR')del(item.path);else alert('Confirmação inválida.')})};
  row.onclick=()=>item.type==='folder'?(currentPath=item.path,load()):openItem(item);
  $('#list').appendChild(row);
 });
}

async function openItem(item){
 if(item.type==='folder'){currentPath=item.path;load();return}
 if(item.media){
   $('#previewTitle').textContent=(isImage(item.mime)?'Visualização de imagem: ':'Visualização de vídeo: ')+item.name;
   $('#preview').innerHTML=isImage(item.mime)
    ? `<img src="${API}?action=media&path=${encodeURIComponent(item.path)}" alt="${esc(item.name)}">`
    : `<video src="${API}?action=media&path=${encodeURIComponent(item.path)}" controls playsinline></video>`;
   openDialog('previewDialog');
   return;
 }
 if(item.editable){
   const d=await api(API+'?action=read&path='+encodeURIComponent(item.path));
   if(!d.ok){alert(d.error);return}
   editorPath=item.path;$('#editorTitle').textContent='Editor de conteúdo — '+item.name;$('#editor').value=d.content||'';openDialog('editorDialog');
   return;
 }
 alert('Este arquivo não possui editor de conteúdo nem visualizador compatível.');
}

function askForm(title,label,value,cb){
 $('#formTitle').textContent=title;$('#formLabel').textContent=label;$('#formInput').value=value||'';formCallback=cb;openDialog('formDialog');setTimeout(()=>$('#formInput').focus(),50)
}
$('#formOk').onclick=async()=>{const v=$('#formInput').value.trim();closeDialog('formDialog');if(formCallback)await formCallback(v);formCallback=null};

$('#newFileBtn').onclick=()=>askForm('Novo arquivo','Nome do arquivo','arquivo.txt',async name=>{
 if(!name)return;const d=await api(API,{method:'POST',body:new URLSearchParams({action:'new_file',path:currentPath,name,content:''})});if(!d.ok)alert(d.error);load()
});
$('#newFolderBtn').onclick=()=>askForm('Nova pasta','Nome da pasta','Nova pasta',async name=>{
 if(!name)return;const d=await api(API,{method:'POST',body:new URLSearchParams({action:'mkdir',path:currentPath,name})});if(!d.ok)alert(d.error);load()
});
$('#refreshBtn').onclick=load;
$('#upBtn').onclick=()=>{if(currentPath){currentPath=currentPath.split('/').slice(0,-1).join('/');load()}};

async function rename(path,name){if(!name)return;const d=await api(API,{method:'POST',body:new URLSearchParams({action:'rename',path,name})});if(!d.ok)alert(d.error);load()}
async function del(path){const d=await api(API,{method:`POST`,body:new URLSearchParams({action:'delete',path})});if(!d.ok)alert(d.error);load()}
async function zipItem(path,name){
 const d=await api(API,{method:'POST',body:new URLSearchParams({action:'zip',path,name:name+'.zip'})});
 if(!d.ok)alert(d.error);else load();
}

$('#saveBtn').onclick=async()=>{
 const d=await api(API,{method:'POST',body:new URLSearchParams({action:'save',path:editorPath,content:$('#editor').value})});
 if(!d.ok)alert(d.error);else closeDialog('editorDialog');
 load();
};

$('#uploadBtn').onclick=()=>{pendingFiles=[];$('#selectedFiles').textContent='';$('#fileInput').value='';openDialog('uploadDialog')};
$('#dropArea').onclick=()=>$('#fileInput').click();
$('#fileInput').onchange=e=>selectFiles([...e.target.files]);
function selectFiles(files){
 pendingFiles=files;
 $('#selectedFiles').innerHTML=files.length?files.map(f=>`• ${esc(f.name)} (${fmt(f.size)})`).join('<br>'):'Nenhum arquivo selecionado.';
}
['dragover','dragenter'].forEach(ev=>$('#dropArea').addEventListener(ev,e=>{e.preventDefault();$('#dropArea').classList.add('drag')}));
['dragleave','drop'].forEach(ev=>$('#dropArea').addEventListener(ev,e=>{e.preventDefault();$('#dropArea').classList.remove('drag')}));
$('#dropArea').addEventListener('drop',e=>selectFiles([...e.dataTransfer.files]));

$('#sendUploadBtn').onclick=()=>{
 if(!pendingFiles.length)return alert('Selecione pelo menos um arquivo.');
 const zips=pendingFiles.filter(f=>ext(f.name)==='zip');
 if(zips.length){zipPending=zips;closeDialog('uploadDialog');openDialog('zipDialog');}
 else upload(false);
};
$('#zipNormalBtn').onclick=()=>{closeDialog('zipDialog');upload(false)};
$('#zipExtractBtn').onclick=()=>{closeDialog('zipDialog');upload(true)};

async function upload(extract){
 const fd=new FormData();fd.append('action','upload');fd.append('path',currentPath);fd.append('extract_zip',extract?'1':'0');
 pendingFiles.forEach(f=>fd.append('files[]',f,f.name));
 $('#progress').style.display='block';$('#progress>div').style.width='35%';
 const d=await api(API,{method:'POST',body:fd});
 $('#progress>div').style.width='100%';
 setTimeout(()=>$('#progress').style.display='none',300);
 if(!d.ok)alert(d.error||'Falha no upload.');
 else if(d.errors?.length)alert('Alguns arquivos tiveram erro:\\n'+d.errors.join('\\n'));
 closeDialog('uploadDialog');pendingFiles=[];zipPending=null;load();
}
load();
</script>
</body>
</html>
