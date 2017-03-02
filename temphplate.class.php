<?php

define ( "_placeholder_open_left", "<!-- %<" );
define ( "_placeholder_open_right", ">% -->" );
define ( "_placeholder_close_left", "<!-- %</" );
define ( "_placeholder_close_right", ">% -->" );
define ( "_occurrences_number_tag", "_OCCNR" );
define ( "_line_break", "\r\n" );
define ( "_list_separator", ", " );

class template
{
    private $source;
    public $target;
    private $data = array ();
    protected $occurrences_count = 0;

    public function __construct ( $source, $data, $data_type = 'variable', $source_type = 'file' )
    {
        if ( in_array ( $source_type, array ( 'file', 'string' ) ) )
        {
            if ( $source_type == 'file' )
                $this -> source = file_get_contents ( $source );
        }
        else
        {
            $this -> source = $source;
        }

        if ( in_array ( $data_type, array ( 'variable', 'object', 'json', 'file' ) ) )
        {
            if ( $data_type == 'variable' || $data_type == 'object' )
                $this -> data = $data;
            elseif ( $data_type == 'json' || $data_type == 'file' )
            {
                if ( $data_type == 'file' )
                    $this -> data = json_decode ( file_get_contents ( $data ) );
                else
                    $this -> data = json_decode ( $data );
            }
        }
        else
        {
            $this -> data = '';
        }
    }

    public function __destruct ()
    {
    }

    public function add ( $pairs, $string = false )
    {
        if ( is_array ( $pairs ) )
        {
            if ( is_array ( $this -> data ) )
                $this -> data = array_merge ( $this -> data, $pairs );
            elseif ( is_object ( $this -> data ) )
                $this -> data = ( object ) array_merge ( ( array ) $this -> data, $pairs );
        }
        elseif ( is_string ( $pairs ) && is_string ( $string ) )
        {
            if ( is_array ( $this -> data ) )
                $this -> data [ $pairs ] = $string;
            elseif ( is_object ( $this -> data ) )
                $this -> data = ( object ) array_merge ( ( array ) $this -> data, array ( $pairs => $string ) );
        }
    }

    private function number_of_occurrences ( $key, $_source = '' )
    {
        if ( $_source == '' )
            $source = $this -> target;
        else
            $source = $_source;
        preg_match_all ( $this -> regexp ( $key, true, true ), $source, $matches );
        $result = count ( $matches [ 0 ] );
        return $result;
    }

    private function is_open_placeholder ( $string )
    {
        $open  = $this -> preg_quote ( _placeholder_open_left );
        $close = $this -> preg_quote ( _placeholder_open_right );
        $re = '/^(?=^' . $open . ')(?=.*' . $close . '$)/im';
        //$re = '/('.$open.'|'.$close.')/umi';
        preg_match ( $re, $string, $matches );
        if ( count ( $matches ) == 1 ) return true;
        return false;
    }

    private function is_close_placeholder ( $string )
    {
        $open  = $this -> preg_quote ( _placeholder_close_left );
        $close = $this -> preg_quote ( _placeholder_close_right );
        $re = '/^(?=^' . $open . ')(?=.*' . $close . '$)/im';
        preg_match ( $re, $string, $matches );
        if ( count ( $matches ) == 1 ) return true;
        return false;
    }

    private function callback_of_numbering_occurrences ( $matches )
    {
        //var_dump($matches);
        if ( $this -> is_close_placeholder ($matches [ 0 ] ) )
            $split = _placeholder_close_right;
        elseif ( $this -> is_open_placeholder ( $matches [ 0 ] ) )
            $split = _placeholder_open_right;
        else
            $split = '';
        $parts = explode ( $split, $matches [ 0 ] );
        $count = $this -> occurrences_count++;
        return $parts [ 0 ] . _occurrences_number_tag . $count . ">% -->";
    }

    private function numbering_occurrences ( $key, &$source )
    {
        $limit = -1;
        $open_count = 0;
        $close_count = 0;
        $this -> occurrences_count = 0;
        $placeholder = $this -> placeholder ( $key );
        //$pattern = "/\<\!\-\- %\<".$key."\>% \-\-\>/umi";
        $pattern =  "/" . $placeholder [ "open" ] . "/umi";
        //$this -> target = preg_replace_callback ( $pattern, array ( $this, 'callback_of_numbering_occurrences' ), $this -> target, $limit, $open_count );
        $source = preg_replace_callback ( $pattern, array ( $this, 'callback_of_numbering_occurrences' ), $source, $limit, $open_count );
        $this -> occurrences_count = 0;
        //$pattern = "/\<\!\-\- %\<\/".$key."\>% \-\-\>/umi";
        $pattern =  "/" . $placeholder [ "close" ] . "/umi";
        //$this -> target = preg_replace_callback ( $pattern, array ( $this, 'callback_of_numbering_occurrences' ), $this -> target, $limit, $close_count );
        $source = preg_replace_callback ( $pattern, array ( $this, 'callback_of_numbering_occurrences' ), $source, $limit, $close_count );
        return $open_count;
    }

    public function render ( $print = true )
    {
        $this -> target = $this -> go ( $this -> source, $this -> data );
        if ( $print )
            print $this -> target;
        else
            return $this -> target;
    }

    private function go ( $_source, $_data )
    {
        $simple_keys    = array ();
        $simple_values  = array ();
        $complex_keys   = array ();
        $complex_values = array ();
        $complex        = array ();

        foreach ( $_data as $key => $value )
        {
            if ( is_array ( $value ) || ( is_object ( $value ) ) )
            {
                if ( $this -> number_of_occurrences ( $key, $_source ) > 1 )
                {
                    $count = $this -> numbering_occurrences ( $key, $_source );
                    for ( $i = 0; $i < $count; $i++ )
                    {
                        $complex [ $key . _occurrences_number_tag . $i ] = $value;
                    }
                }
                else
                    $complex [ $key ] = $value;
            }
            else
            {
                $simple_keys [] = $this -> regexp ( $key, false, false );
                $simple_values [] = $value;
            }
        }

        foreach ( $complex as $key => $value )
        {
            //echo ("key:\n");
            //var_export($key);
            //echo ("\n\n");
            //echo ("value:\n");
            //var_export($value);
            //echo ("\n\n");
            $regexp_key = $this -> regexp ( $key, false, false );
            $complex_keys [] = $regexp_key;
            $complex_source = $this -> get_source ( $key, $_source );
            //$complex_source = '';
            //echo ("\ncomplex_source:\n");
            //var_export($complex_source);
            //echo ("\n\n");
            //$complex_values [] = $this -> dig ( $key, $value, $complex_source ); // milyen source?
            //echo (gettype($value));
            if ( $this -> indexed_array ( $value ) )
            {
                //$complex_values [] = $this -> listing ( $key, $value, $complex_source );
                $indexed_array_result = '';
                foreach ( $value as $indexed_array_index => $indexed_array_value )
                {
                    //echo ("<br>indexed_array_index=".$indexed_array_index."<br>");
                    //var_dump($indexed_array_value);
                    if ( ( is_string ( $indexed_array_value ) ) || ( is_numeric ( $indexed_array_value ) ) || ( is_bool ( $indexed_array_value ) ) )
                    {
                        $indexed_array_result .= $indexed_array_value . _list_separator;
                    }
                    else
                        $indexed_array_result .= $this -> go ( $complex_source, $indexed_array_value );
                }
                ///echo ("\n");
                $indexed_array_result = rtrim($indexed_array_result,_list_separator);
                $complex_values [] = $indexed_array_result;
            }
            else
                $complex_values [] = $this -> go ( $complex_source, $value );
            //echo ("complex_keys:\n");
            //var_export($complex_keys);
            //echo ("\n");
            //echo ("complex_values:\n");
            //var_export($complex_values);
            //echo ("\n");
        }
        unset ( $complex );

        $limit = -1;
        $count = 0;
        $_source = preg_replace ( $complex_keys, $complex_values, $_source, $limit, $count );
        unset ( $complex_keys );
        unset ( $complex_values );

        $limit = -1;
        $count = 0;
        $_source = preg_replace ( $simple_keys, $simple_values, $_source, $limit, $count );
        unset ( $simple_keys );
        unset ( $simple_values );

        return $_source;

    }

    /*
    private function listing ( $source_key, $data, $_source = '' )
    {
        echo ("\nlisting called\n\n");
        echo ("source_key=".$source_key."\n");
        echo ("data:\n");
        var_export($data);
        echo ("\n");
        $keys = array ();
        $values = array ();
        if ( $_source == '' )
            $source = $this -> get_source ( $source_key );
        else
            $source = $_source;
        echo ("source=".$source."\n");
        foreach ( $data as $key => $value )
        {
            if ( is_string ( $value ) || is_numeric ( $value ) )
            {
                $keys[] = $this -> regexp ( $key, false, false );
                $values[] = $value;
            }
            elseif ( is_array ( $value ) || is_object ( $value ) )
            {
                echo ("\nhere\n");
                echo ("key=".$key."\n");
                if ( $this -> number_of_occurrences ( $key, $source ) > 1 )
                {
                    $changes = $this -> numbering_occurrences ( $key, $source );
                    for ( $i = 0; $i < $changes; $i++ )
                    {
                        $subsource = $this -> get_source ( $key . _occurrences_number_tag . $i, $source );
                        $keys[] = $this -> regexp ( $key . _occurrences_number_tag . $i, false, false );
                        $values[] = '';//$this -> dig ( $key, $value, $subsource );
                    }
                }
                else
                {
                    $keys[] = $this -> regexp ( $key, false, false );
                    $values[] =  '';//$this -> dig ( $key, $value );
                }
            }
        }
        $limit = -1;
        $count = 0;
        echo ("\nlisting finished\n\n");
        return preg_replace ( $keys, $values, $source, $limit, $count );
    }
    */

    /*
    private function dig ( $render_key, $render_value, $render_source = '' )
    {
        if ( $render_source == '' )
            $render_source = $this -> get_source ( $render_key, $this -> source ); //Kell ez?
        echo ("\n----------- dig starts ------------\n");
        echo ("\nrender_key: ".$render_key."\n");
        echo ("\nrender_value:\n");
        var_export($render_value);
        echo ("\nrender_source:\n");
        var_export($render_source);
        echo ("\n");
        $result = '';
        if ( is_array ( $render_value ) && $this -> indexed_array ( $render_value ) )
        {
            foreach ( $render_value as $index => $value )
            {
                $result .= $this -> listing ( $render_key, $value, $render_source );
            }
        }
        else
        {
            if ( is_object ( $render_value ) || is_array ( $render_value ) )
            {
                foreach ( $render_value as $sub_render_key => $sub_render_value )
                {
                    //$result .= $this -> dig ( $sub_render_key, $sub_render_value, $this -> get_source ( $render_key ) );
                    $result .= $this -> dig ( $sub_render_key, $sub_render_value, $render_source );
                }
            }
            else
            {
                echo ("\nkey ");
                var_dump($render_key);
                echo ("\nvalue ");
                var_dump($render_value);
                echo ("\nsource ");
                var_dump($render_source);
                echo ("\n ");
                //$sub_source = $this -> get_source ( $render_key, $render_source );
                //var_dump($sub_source);
                echo ("hmm\n ");

                //$result .= $sub_source;
                //$result .= preg_replace ( array ( 0 => $render_key ), array ( 0 => $render_value ), $render_source, -1 );
            }
        }
        echo ("\n----------- dig ends ------------\n");
        return $result;
    }
    */
    
    private function indexed_array ( $arr )
    {
        if ( !is_array ( $arr ) ) return false;
        return array_keys ( $arr ) === range ( 0, ( count ( $arr ) - 1 ) );
        //return array_merge($arr) === $arr;
    }

    private function get_source ( $key, $_source = '' )
    {
        //echo ("\nget_source\n");
        //echo ("key:\n");
        //var_export($key);
        //echo ("\nsource:\n");
        //var_export($_source);
        //echo ("\n");
        if ( $_source == '' )
            preg_match_all ( $this -> regexp ( $key, true, true ), $this -> target, $matches );
        else
            preg_match_all ( $this -> regexp ( $key, true, true ), $_source, $matches );
        if ( isset ( $matches [ 0 ] [ 0 ] ) ) return $matches [ 0 ] [ 0 ]; else return '';
    }

    private function placeholder ( $key, $escape = true )
    {
        $result = array ();
        $result [ 'open' ] = _placeholder_open_left . $key . _placeholder_open_right;
        $result [ 'close' ] = _placeholder_close_left . $key . _placeholder_close_right;
        if ( $escape )
        {
            $result [ 'open' ] = $this -> preg_quote ( $result [ 'open' ] );
            $result [ 'close' ] = $this -> preg_quote ( $result [ 'close' ] );
        }
        return $result;
    }

    private function regexp ( $key, $lookahead = true, $lookbehind = true )
    {
        $placeholder = $this -> placeholder ( $key );
        if ( $lookbehind ) { $lookbehind = '?<='; } else { $lookbehind = ''; }
        if ( $lookahead ) { $lookahead = '?='; } else { $lookahead = ''; }
        $result = '/(?s)(' . $lookbehind . $placeholder [ 'open' ] . ').*?(' . $lookahead . $placeholder [ 'close' ] . ')/umi';
       return $result;
    }

    private function preg_quote ( $string )
    {
        $string = preg_quote ( $string );
        $string = str_replace ( '/', '\/', $string );
        return $string;
    }

/*
    public function source ( $_source, $_type = 'file' )
    {
        if ( in_array ( $_type, array ( 'file', 'string' ) ) )
        {
            if ( $_type == 'file' ) $this -> source = file_get_contents ( $_source );
        }
        else
        {
            $this -> source = $_source;
        }
    }
*/
/*
    public function simple ( $source, $items, $limit = -1 )
    {
        $result = $source;
        $count = 0;
        foreach ( $items as $key => $value )
        {
            if ( is_array ( $value ) or is_object ( $value ) )
            {
                $replacement = $this -> liszting ( $key, $value );
                $result = preg_replace ( $this -> regexp ( $key ), $replacement, $result, $limit, $count );
            }
            else
                $result = preg_replace ( $this -> regexp ( $key ), $value, $result, $limit, $count );
        }
        return $result;
    }

    private function liszting ( $parent, $items, $source = '' )
    {
        $result = '';
        if ( $source == '' )
        {
            $source = $this -> source;
            preg_match_all ( $this -> regexp ( $parent, true, true ), $source, $matches );
            $source = $matches [ 0 ] [ 0 ];
        }
        foreach ( $items as $index => $item )
        {
            $result .= $this -> simple ( $source, $item );
        }
        return $result;
    }

    public function replace_section ( $key, $value, $source = '', $limit = 1 )
    {
        if ( $source == '' ) $source = $this -> source;
        $count = 0;
        return preg_replace ( $this -> regexp ( $key ), $value, $source, $limit, $count );
    }
*/
/*
        public function replace ( $pattern, $text )
        {
            $this -> occurrences_count = 0;
            return preg_replace_callback ( $pattern, array ( $this, '_callback' ), $text );
        }
    */

}

