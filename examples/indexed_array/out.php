<?php
//header("Content-Type: text/plain");
$template = new template ( dirname ( __FILE__ ) . '/source.html', dirname ( __FILE__ ) . '/data.json', 'file', 'file' );
$template -> render ();
