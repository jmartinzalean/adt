<?php

/*
Plugin Name: adtws
Plugin URI: zalean.com
Description: Plugin para conexion con Witei
Version: 1.0.1
Author: juanantonio
Author URI: zalean.com
License: GPLv2
*/

/* 
Copyright (C) 2019 juanantonio

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/
defined('ABSPATH') or die();

include_once dirname(__FILE__).'/includes/AdtAjax.php';

new AdtAjax();

function adtws_activate() {

}

function adtws_desactivate() {

}


