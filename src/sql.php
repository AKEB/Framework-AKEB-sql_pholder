<?php

namespace AKEB;

class sql_pholder {

	// При ошибке в sql_placeholder_ex() возвращается запрос с
	// указанным ниже префиксом.
	const PLACEHOLDER_ERROR_PREFIX = "ERROR: ";
	const PLACEHOLDER_NEED_ADDSLASHES = true;

	// function sql_placeholder(mixed $tmpl, $arg1 [,$arg2 ...])
	//
	// Замечание: см. описание функции sql_placeholder_ex() выше.
	//
	// Возвращает результирующий запрос после всех подстановок.
	// В случае ошибки запрос будет содержать префикс "ERROR: ".
	//
	// Если во время подстановки произошла ошибка, (например, несоответствие
	// типов), вставляет вместо значений placeholder-ов текстовое сообщение
	// об ошибке и возвращает запрос в следующем виде:
	//   "ERROR: шаблон с проставленными сообщениями".
	// Такой запрос, конечно, породит ошибку при попытке своего выполнения.
	// Вы также можете проанализировать возвращенное значение: если оно
	// начинается со строки "ERROR: ", подстановка окончилась неудачей.
	//
	// Вместо того, чтобы использовать массив в качестве второго параметра,
	// Вы можете передать значения всех неименованных placeholder-ов одно
	// за одним.
	//
	// Если же в шаблоне есть хотя бы один именованный placeholder, функция
	// ОБЯЗАНА принимать в точности два параметра, где первый - это шаблон,
	// а второй - ассоциативный массив для подстановки значений именованных
	// placeholder-ов.
	public static function sql_placeholder() {
		$args = func_get_args();
		$tmpl = array_shift($args);
		$error = "";
		$result = static::sql_placeholder_ex($tmpl, $args, $error);
		if ($result === false) return static::PLACEHOLDER_ERROR_PREFIX.$error;
		else return $result;
	}


	// function sql_pholder(mixed $tmpl, $arg1 [,$arg2 ...])
	//
	// Замечание: см. описание функции sql_placeholder() выше.
	//
	// Функция работает точно так же, как sql_placeholder(), однако
	// в случае ошибки она возвращает false и генерирует предупреждение
	// стандартными средствами, используя trigger_error().
	public static function sql_pholder() {
		$args = func_get_args();
		$tmpl = array_shift($args);
		$error = "";
		$result = static::sql_placeholder_ex($tmpl, $args, $error);
		if ($result === false) {
			$error = "Placeholder substitution error. Diagnostics: \"$error\"";
			if (function_exists("debug_backtrace")) {
				$bt = debug_backtrace();
				$error .= " in ".@$bt[0]['file']." on line ".@$bt[0]['line'];
			}
			trigger_error($error, E_USER_WARNING);
			return false;
		}
		return $result;
	}

	// bool sql_placeholder_ex(mixed $tmpl, array $args, string &$errormsg)
	//
	// Заменяет все placeholder-ы в $tmpl на их SQL-экранированные значения
	// из $args. При ошибке сохраняет диагностическое сообщение в $errormsg.
	//
	// Различные типы placeholder-ов:
	//   ?  - заменяется на ОДНО скалярное значение.
	//   ?@ - заменяется на СПИСОК: 'a', 'b', ... (например, удобно
	//        использовать в запросе "SELECT ... WHERE id IN ( ?@ )")
	//   ?% - заменяется на список пар ключ=значение: k1='v1', k2='v2', ...
	//        (удобно использовать в запросах "UPDATE ... SET ?%")
	//
	// Placeholder-ы могут быть именованными: их имя можно указывать сразу
	// после спецификатора типа, например: "?k", "?@k", "?%k".
	//
	// Параметр $tmpl может содержать не только текстовое представление
	// шаблона, но и результат работы функции sql_compile_placeholder().
	// Это удобно, если нужно несколько раз выполнить SQL-запрос, имеющий
	// один и тот же шаблон, но разные параметры.
	//
	// Если в шаблоне есть хотя бы один именованный placeholder,
	// $args должен содержать список из ЕДИНСТВЕННОГО элемента. Этот
	// элемент сам является ассоциативным массивом, содержащим имена
	// placeholder-ов и соответствующие им значения.
	//
	// Если при подстановке  возникнут ошибки (например, несоответствие
	// типов placeholder-а и подставляемого значения, недопустимое имя
	// или номер placeholder-а и т.д.), в результирующий запрос вместо
	// значения placeholder-а вставляется диагностическое сообщение.
	// При этом функция возвращает false, а получившийся "фальшивый"
	// запрос помещается в переменную $errormsg.
	private static function sql_placeholder_ex($tmpl, $args, &$errormsg) {
		// Запрос уже разобран?.. Если нет, разбираем.
		if (is_array($tmpl)) {
			$compiled = $tmpl;
		} else {
			$compiled  = static::sql_compile_placeholder($tmpl);
		}

		list ($compiled, $tmpl, $has_named) = $compiled;

		// Если есть хотя бы один именованный placeholder, используем
		// первый аргумент в качестве ассоциативного массива.
		if ($has_named) $args = @$args[0];

		// Выполняем все замены в цикле.
		$p   = 0;       // текущее положение в строке
		$out = '';      // результирующая строка
		$error = false; // были ошибки?

		foreach ($compiled as $num=>$e) {
			list ($key, $type, $start, $length) = $e;

			// Pre-string.
			$out .= mb_substr($tmpl, $p, $start - $p);
			$p = $start + $length;

			$repl = '';   // текст для замены текущего placeholder-а
			$errmsg = ''; // сообщение об ошибке для этого placeholder-а
			do {
				// Это placeholder-константа?
				if ($type === '#') {
					$repl = @constant($key);
					if (NULL === $repl)
						$error = $errmsg = "UNKNOWN_CONSTANT_$key";
					break;
				}
				// Обрабатываем ошибку.
				if (!isset($args[$key])) {
					$error = $errmsg = "UNKNOWN_PLACEHOLDER_$key";
					break;
				}
				// Вставляем значение в соответствии с типом placeholder-а.
				$a = $args[$key];
				if ($type === '') {
					// Скалярный placeholder.
					if (is_array($a)) {
						$error = $errmsg = "NOT_A_SCALAR_PLACEHOLDER_$key";
						break;
					}
					$repl = "'".(static::PLACEHOLDER_NEED_ADDSLASHES ? addslashes($a): $a)."'";
					break;
				}
				// Иначе это массив или список.
				if (!is_array($a)) {
					$error = $errmsg = "NOT_AN_ARRAY_PLACEHOLDER_$key";
					break;
				}
				if ($type === '@') {
					// Это список.
					foreach ($a as $v)
						$repl .= ($repl===''? "" : ",").(isset($v) ? "'".(static::PLACEHOLDER_NEED_ADDSLASHES ? addslashes($v): $v)."'": 'NULL');
				} elseif ($type === '%') {
					// Это набор пар ключ=>значение.
					$lerror = [];
					foreach ($a as $k=>$v) {
						if (!is_string($k)) {
							$lerror[$k] = "NOT_A_STRING_KEY_{$k}_FOR_PLACEHOLDER_$key";
						} else {
							$k = preg_replace('/[^a-zA-Z0-9_]/', '_', $k);
						}
						$repl .= ($repl===''? "" : ", ")."`".$k."`=".(isset($v) ? "'".(static::PLACEHOLDER_NEED_ADDSLASHES ? addslashes($v): $v)."'": 'NULL');
					}
					// Если была ошибка, составляем сообщение.
					if (count($lerror)) {
						$repl = '';
						foreach ($a as $k=>$v) {
							if (isset($lerror[$k])) {
								$repl .= ($repl===''? "" : ", ").$lerror[$k];
							} else {
								$k = preg_replace('/[^a-zA-Z0-9_-]/', '_', $k);
								$repl .= ($repl===''? "" : ", ").$k."=?";
							}
						}
						$error = $errmsg = $repl;
					}
				}
			} while (false);
			if ($errmsg) $compiled[$num]['error'] = $errmsg;
			if (!$error) $out .= $repl;
		}
		$out .= mb_substr($tmpl, $p);

		// Если возникла ошибка, переделываем результирующую строку
		// в сообщение об ошибке (расставляем диагностические строки
		// вместо ошибочных placeholder-ов).
		if ($error) {
			$out = '';
			$p   = 0;       // текущая позиция
			foreach ($compiled as $num=>$e) {
				list ($key, $type, $start, $length) = $e;
				$out .= mb_substr($tmpl, $p, $start - $p);
				$p = $start + $length;
				if (isset($e['error'])) {
					$out .= $e['error'];
				} else {
					$out .= mb_substr($tmpl, $start, $length);
				}
			}
			// Последняя часть строки.
			$out .= mb_substr($tmpl, $p);
			$errormsg = $out;
			return false;
		} else {
			$errormsg = false;
			return $out;
		}
	}

	// function sql_compile_placeholder(string $tmpl)
	// Разбирает шаблон запроса и сохраняет положения всех
	// placeholder-ов в нем для дальнейшей быстрой подстановки.
	// Возвращает структуру вида:
	// list(
	//   list(
	//     $key,    // имя placeholder-а
	//     $type,   // '@'|'%'|'#'|''
	//     $start,  // положение placeholder-а
	//     $length  // длина placeholder-а
	//   ),
	//   $tmpl,     // исходный шаблон запроса
	//   $has_named // есть ли в шаблоне именованный placeholder?
	// )
	private static function sql_compile_placeholder($tmpl) {
		$compiled  = [];
		$p         = 0;  // текущая позиция в строке
		$i         = 0;  // счетчик placeholder-ов
		$has_named = false;
		while (false !== ($start = $p = mb_strpos($tmpl, "?", $p))) {
			// Определяем тип placeholder-а.
			switch ($c = mb_substr($tmpl, ++$p, 1)) {
				case '%': case '@': case '#':
					$type = $c; ++$p; break;
				default:
					$type = ''; break;
			}
			// Проверяем, именованный ли это placeholder: "?keyname"
			if (preg_match('/^((?:[^\s[:punct:]]|_)+)/', mb_substr($tmpl, $p), $pock)) {
				$key = $pock[1];
				if ($type != '#') $has_named = true;
				$p += mb_strlen($key);
			} else {
				$key = $i;
				if ($type != '#') $i++;
			}
			// Сохранить запись о placeholder-е.
			$compiled[] = [$key, $type, $start, $p - $start];
		}
		return [$compiled, $tmpl, $has_named];
	}

}