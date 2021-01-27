<?php



# === configuration {
  define('PATH', realpath('/var/www/test'));
  define('TAB_SIZE', 2);
  define('EDITABLE_MIME_REG', ['/text\/.+/', '/inode\/x-empty/']);
  # define('LOGIN', 'developer');
  # define('PASSWORD', 'changethis');
# } configuration ===



# === auth {
if ( defined('LOGIN') && defined('PASSWORD') ) {
	$allow = !empty($_SERVER['PHP_AUTH_USER']) && ($_SERVER['PHP_AUTH_USER'] == LOGIN ) &&
	         !empty($_SERVER['PHP_AUTH_PW']) && ($_SERVER['PHP_AUTH_PW'] == PASSWORD );
  
  if ( !$allow ) {
    header('HTTP/1.1 401 Authorization Required');
		header('WWW-Authenticate: Basic realm="Access denied"');
		exit;
  }
}
# } auth ===



# === file management backend {
  if ( !is_writable(PATH) ) {
    $error = 'Please make code directory (' . PATH . ') writable for PHP';
  }
  
  # load or read code for a file (AJAX)
  if ( $_GET['f'] ) {
    $file = PATH . '/' . ltrim($_GET['f'], '/.');
    
    if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
      $success = file_put_contents($file, file_get_contents('php://input'));
      die( json_encode(['written' => $success === false ? false : true]) );
    }
    else {
      $writable = is_writable($file);
      $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
      $editable = pathinfo($file, PATHINFO_EXTENSION) == 'txt';
      
      if ( !$editable ) {
        foreach ( EDITABLE_MIME_REG as $mime_pattern ) {
          if ( preg_match($mime_pattern, $mime) ) {
            $editable = true;
          }
        }
      }
      
      if ( $editable ) {
        $code = file_get_contents($file);
      }

      die( json_encode(['code' => $code, 'writable' => $writable, 'mime' => $mime, 'editable' => $editable]) );
    }
  }
  
  # init create default file/dirs if specified and new
  if ( $_GET['p'] ) {
    $default_file = $_GET['p'];
    $file = PATH . '/' . ltrim($default_file, '/.');
    if ( !is_file($file) ) {
      $dir = dirname($file);
      
      if ( !is_dir($dir) ) {
        mkdir($dir, 0755, true);
      }
      
      $success = file_put_contents($file, '');
      if ( $success === false ) {
        $error = 'Unable to create "' . $default_file . '"';
      }
    }
  }
  
  # remove file
  if ( $_GET['r'] ) {
    $file = PATH . '/' . ltrim($_GET['r'], '/.');
    if ( !unlink($file) ) {
      $error = 'Unable to remove "' . $default_file . '"';
    }
    else {
      die(header('Location: ' . parse_url($_SERVER['REQUEST_URI'])['path']));
    }
  }
# } file management backend ===



# === utilities {
  # build html(ul/li) file tree
  function tree($dir = null) {
    if ( !$dir ) $dir = PATH;
    $html = '';
  
    foreach ( glob( $dir . '/*' ) as $file ) {
      if ( is_dir($file) ) {
        $tree_html = tree($file);
        if ( $tree_html ) {
          $html .= '<li class="dir"><b>' . basename($file) . '/</b>' .
                      '<ul>' . $tree_html . '</ul>' .
                    '</li>';
        }
      }
      else {
        $name = str_replace($dir . '/', '', $file);
        $path = str_replace(PATH . '/', '', $file) ;
        $html .= '<li><i data-file="' . $path . '">' . $name . '</i></li>';
      }
    }
  
    return $html;
  }
# } utilities ===



# === render UI { ?>
  <html>
    <head>
      <title>Tiny Web IDE</title>
      <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Mono">
      <style>
        body                        { margin: 0; border: 0; padding: 0; font-family: Roboto Mono, monospace;
                                      font-size: 12px; background: #272822; }
      
        #files                      { position: fixed; top: 0; width: 20%; left: 0; bottom: 0;
                                      margin: 0; padding: .5em 0 0 .9em; background: #202020; list-style: none;
                                      color: #F8F8F2; box-sizing: border-box; overflow: auto; }
        #files ul                   { margin: 0; padding: 0 0 0 1em; list-style: none; display: none; border-left: 1px solid #333; }
        #files ul.open              { display: block; }
        
        #files li                   { margin: 2px 0; }
        #files li i, #files li b    { font-style: normal; cursor: pointer; border-radius: 4px;
                                      padding: 1px 3px; opacity: 0.5; margin-left: -5px; }
        #files li i:hover,
        #files li b:hover           { background: #444; }
        #files li i.edit            { background: #666; opacity: 1; cursor: default; }
        #files li i.load            { background: #555; opacity: 0.75; cursor: default; }
        #files .save                { color: #666; font-size: 10px; font-style: normal; margin: 0 0 0 1em; }
        
        #editor_container           { position: fixed; left: 20%; top: 0; bottom: 0; right: 0; }
        #editor_container #editor   { position: absolute; top: 0; left: 0; bottom: 0; right: 0; }
        
        #error                      { position: fixed; right: 2em; top: 2em; background: #FF4136;
                                      padding: 1em; color: #fff; display: none; }
        #error.on                   { display: block; }
        #error i                    { margin: 0 0 0 1em; background: #444; cursor: pointer; font-style: normal;
                                      padding: 2px 6px; border-radius: 4px; opacity: 0.75; }
        #error i:hover              { opacity: 1; }
        
        #search                     { position: fixed; top: 50%; left: 50%; width: 300px; height: 35px; margin-left: -150px;
                                      margin-top: -17px; display: none; }
        #search.on                  { display: block; }
        #search input               { border: none; outline: none; width: 300px; height: 35px; padding: 1px 8px;
                                      font-size: 18px; font-family: Roboto Mono, monospace; box-shadow: 0 0 8px #fff;
                                      border-radius: 2px; }
        #search ul                  { list-style: none; padding: 0; margin: 8px 0 0 0; }
        #search ul li b             { color: yellow; }
        #search ul li               { background: #000; padding: 6px 8px; color: #fff; opacity: 0.5; }
        #search ul li.on,
        #search ul li:hover         { background: #000; cursor: pointer; opacity: 1; }
        
        #help                       { position: fixed; bottom: 1em; right: 1em; color: #fff; }
      </style>
    </head>
    <body>
      
      
      
      <?php /* Client code editor & navigation app { */ ?>
        <ul id="files"><?=tree()?></ul>
        
        <div id="editor_container">
          <div id="editor"></div>
        </div>
        
        <div id="search">
          <input placeholder="Search files...">
          <ul></ul>
        </div>
        
        <div id="error"></div>
        <a id="help" target="_blank" href="https://github.com/zeroapps/tiny-web-ide">help</a>
      <?php /* } */ ?>
      
      
      
      <?php /* Client code editor & navigation app { */ ?>
      
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"
                integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg=="
                crossorigin="anonymous"></script>
      
        <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.5/ace.js"
                integrity="sha256-5Xkhn3k/1rbXB+Q/DX/2RuAtaB4dRRyQvMs83prFjpM="
                crossorigin="anonymous"></script>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.5/ext-modelist.js"
                integrity="sha256-Eq83mfVraqpouXYtwjYSEvTt6JM7k1VrhsebwRs06AM="
                crossorigin="anonymous"></script>
      
        <script>
          // init editor with specified settings
          var editor = null; // global editor variable
          function init_editor() {
            editor = ace.edit("editor", {
              theme: 'ace/theme/monokai',
              fontFamily: 'Roboto Mono',
              tabSize: <?=TAB_SIZE?>,
              useSoftTabs: true,
              readOnly: true
            });
          }
          
          // load code for previously selected file (using "#" in address)
          function init_default_file() {
            var file = <?=json_encode($default_file)?>;
            if ( file ) {
              var file_element = $('i[data-file="' + file + '"]');
              file_element.click();
              
              var parent = file_element[0];
              while ( parent = $(parent).parent()[0] ) {
                if ( $(parent).hasClass('dir') ) {
                  $(parent).children('ul').addClass('open');
                }
              }
            }
          }
          
          // listen to folders and files events
          function init_tree() {
            // Folder toggling
            $(document).on('click', '#files b', function() {
              $(this).parent().children('ul').toggleClass('open');
            });
            
            $(document).on('click', '#files i', function() {
              if ( $('#files i.load').length > 0 ) return; // cancel if we're loading a file already
              if ( $(this).hasClass('edit') ) return; // cancel if this file is loaded already
              
              $('#files i.edit').removeClass('edit').parent().find('.save').remove();
              $(this).addClass('load');
              
              load_code();
            });
            
            $(document).on('dblclick', '#files i', function(e) {
              e.stopPropagation();
              e.preventDefault();
              
              var file = $(this).data('file');
              if ( confirm('Remove "' + file + '"?') ) {
                location = location.pathname + '?r=' + file;
              }
            });
          }
          
          // code search listener
          function init_search() {
            $(document).on('keydown', function(e) {
              if ( (e.ctrlKey || e.metaKey) && e.shiftKey && e.keyCode == 70 && !$('#search').hasClass('on') ) { // listen to "F" key
                $('#search').addClass('on');
                $('#search input').focus().select();
              }
            });
            
            $(document).on('blur', '#search input', function(e) {
              setTimeout(function() {
                $('#search').removeClass('on');
              }, 200);
            });
            
            $(document).on('keydown', '#search input', function(e) {
              if ( e.keyCode == 13 ) {
                if ( $('#search ul li.on')[0] ) {
                  $('#files i[data-file="' + $('#search ul li.on').text() + '"]').click();
                }
              }
              else if ( e.keyCode == 27 ) {
                $('#search').removeClass('on');
              }
            });
            
            $(document).on('keyup', '#search input', function(e) {
              
              if ( e.keyCode == 40 ) {
                var next = $('#search ul li.on').next()[0] || $('#search ul li')[0];
                $('#search ul li.on').removeClass('on');
                $(next).addClass('on');
              }
              else if ( e.keyCode == 38 ) {
                var next = $('#search ul li.on').prev()[0] || $('#search ul li:last')[0];
                $('#search ul li.on').removeClass('on');
                $(next).addClass('on');
              }
              else {
                var q = $(this).val();
                var possible = [];
                
                if ( q != '' ) {
                  $('#files i').each(function() {
                    if ( ( possible.length < 10 ) && ($(this).data('file').indexOf(q) >= 0) ) {
                      possible.push('<li>' + $(this).data('file').replace(q, '<b>' + q + '</b>') + '</li>');
                    }
                  });
                }
                
                $('#search ul').html(possible.join(''));
              }
            });
            
            $(document).on('click', '#search ul li', function() {
              $('#files i[data-file="' + $(this).text() + '"]').click();
            });
          }
          
          
          
          // load code for currently selected file
          var change_cb; // global editor code change callback (to disable/enable it)
          function load_code() {
            if ( change_cb ) {
              editor.getSession().off('change', change_cb);
            }
            
            var file = $('#files i.load').data('file');
            fetch('?f=' + file, {
            }).then(function(response) {
              return response.json();
            }).then(function(data) {
              window.history.pushState({}, file, '?p=' + file);
              document.title = file;
              
              $('#files i.load').removeClass('load').addClass('edit');
              
              
              if ( data.writable && data.editable ) {
                editor.setValue(data.code, -1);
                var modelist = ace.require("ace/ext/modelist");
                var mode = modelist.getModeForPath(file).mode;
                editor.session.setMode(mode);
                
                editor.setReadOnly(false);
                editor.focus();
                
                editor.getSession().setUndoManager(new ace.UndoManager());
                
                change_cb = function() {
                  save_code($('#files i.edit').data('file'), editor.getValue());
                };
                editor.getSession().on('change', change_cb);
              } else {
                if ( !data.editable ) {
                  error('"' + file + '" is not editable text file');
                }
                else {
                  error('"' + file + '" is not writable');
                }
              }
            }).catch((message) => {
              error(message);
              console.log(message);
            });
          }
          
          // save code through backend
          var save_in_progress = false;
          var queued_save = null;
          function save_code(file, code) {
            if ( !file ) return;
            
            if ( save_in_progress ) {
              if ( queued_save ) {
                clearTimeout(queued_save);
              }
              return queued_save = setTimeout(function() { save_code(file, code); }, 25);
            }
            
            save_in_progress = true;
            var file_element = $('#files i[data-file="' + file + '"]').parent();
            if ( !file_element.find('.save').length ) {
              file_element.append('<em class="save"></em>');
            }
            file_element.find('.save').text('saving...');
            fetch('?f=' + file, {
                method: 'post',
                body: code
            }).then(function(response) {
              return response.json();
            }).then(function(data) {
              save_in_progress = false;
              if ( !data.written ) {
                error('"' + file + '" code not saved');
              }
              else {
                file_element.find('.save').text('saved');
              }
            }).catch((error) => {
              save_in_progress = false;
              error(error);
            });
          }
          
          
          
          // error alert
          function error(message) {
            $('#error').html( message + '<i>&times;</i>' ).addClass('on');
          }
          
          // error interaction
          function init_error(startup_error) {
            $(document).on('click', '#error i', function() {
              $('#error').removeClass('on');
            })
            
            if ( startup_error ) {
              error(startup_error);
            }
          }
        
        
        
          // launch app
          $(document).ready(function() {
            init_editor();
            init_tree();
            init_error(<?=json_encode($error)?>);
            init_default_file();
            init_search();
          });
          
        </script>
      <?php /* } */ ?>
      
    </body>
  </html>
<?php # } render UI ===