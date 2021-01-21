# tiny-web-ide
Minimal web single-script IDE based on PHP that can be easily integrated into any online web app.


## What is it and why this kind IDE?
1. This is a super light PHP-based online code editor for fast code editing, right on the server.
1. Single file "ide.php" can be placed anywhere on the server, pointed to a certain directory and accessed from a web browser.
1. The idea is not to replace advanced IDEs/code-editors, but rather have in-place code editor to quickly build prototypes, test features and create micro apps.


![IDE Screenshot](https://raw.githubusercontent.com/zeroapps/tiny-web-ide/main/docs/ide.png)


## Features
1. File tree control based on predefined path.
1. Edit any type of textual files (any known language).
1. Files are automatically synced with backend while being edited.
1. Simple new files/dirs creation using browser address bar.
1. It uses https://github.com/ajaxorg/ace as a code editor, so all the features included:
   - syntax highlighting
   - regex-enabled find/replace
   - line numbers
   - coding tips


## Installation
Copy ide.php file onto your server where your code resides:
```
wget https://raw.githubusercontent.com/zeroapps/tiny-web-ide/main/ide.php
```

Open the file and set your directory with the code you want to edit:
```
# === configuration {
  define('PATH', realpath('/var/www/test'));
```

## Configuration
Set the desired tab size:
```
define('TAB_SIZE', 2);
```

Edit list of editable files (each element in array is a regex of MIME types allowed to be edited):
```
define('EDITABLE_MIME_REG', ['/text\/.+/', '/inode\/x-empty/']);
```

## Usage
### Creating files
Just add "?p=new_file" to the address bar to create new_file. All new subdirectories will be created automatically:

![IDE - create new file](https://raw.githubusercontent.com/zeroapps/tiny-web-ide/main/docs/ide_new_file.png)


### Removing files
In order to remove file double click on it in a file tree:

![IDE - remove file](https://raw.githubusercontent.com/zeroapps/tiny-web-ide/main/docs/ide_remove_file.png)



## Technologies used
1. ACE editor: https://github.com/ajaxorg/ace
1. jQuery: https://github.com/jquery/jquery