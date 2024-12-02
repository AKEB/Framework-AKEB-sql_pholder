<?php

function sql_pholder() {
	$args = func_get_args();
	return call_user_func_array('\AKEB\sql_pholder::sql_pholder', $args);
}

function sql() {
	$args = func_get_args();
	return call_user_func_array('\AKEB\sql_pholder::sql_pholder', $args);
}