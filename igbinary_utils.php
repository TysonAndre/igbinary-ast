<?php
/* {{{ Types */
const igbinary_type_null          = "\x00";			/**< Null. */

const igbinary_type_ref8  = "\x01";			/**< Array reference. */
const igbinary_type_ref16 = "\x02";			/**< Array reference. */
const igbinary_type_ref32 = "\x03";			/**< Array reference. */

const igbinary_type_bool_false = "\x04";		/**< Boolean true. */
const igbinary_type_bool_true  = "\x05";		/**< Boolean false. */

const igbinary_type_long8p  = "\x06";			/**< Long 8bit positive. */
const igbinary_type_long8n  = "\x07";			/**< Long 8bit negative. */
const igbinary_type_long16p = "\x08";			/**< Long 16bit positive. */
const igbinary_type_long16n = "\x09";			/**< Long 16bit negative. */
const igbinary_type_long32p = "\x0a";			/**< Long 32bit positive. */
const igbinary_type_long32n      = "\x0b";			/**< Long 32bit negative. */

const igbinary_type_double       = "\x0c";			/**< Double. */

const igbinary_type_string_empty = "\x0d";	/**< Empty string. */

const igbinary_type_string_id8   = "\x0e";		/**< String id. */
const igbinary_type_string_id16  = "\x0f";		/**< String id. */
const igbinary_type_string_id32  = "\x10";		/**< String id. */

const igbinary_type_string8      = "\x11";			/**< String. */
const igbinary_type_string16     = "\x12";		/**< String. */
const igbinary_type_string32     = "\x13";		/**< String. */

const igbinary_type_array8       = "\x14";			/**< Array. */
const igbinary_type_array16      = "\x15";			/**< Array. */
const igbinary_type_array32      = "\x16";			/**< Array. */

const igbinary_type_object8      = "\x17";			/**< Object. */
const igbinary_type_object16     = "\x18";		/**< Object. */
const igbinary_type_object32     = "\x19";		/**< Object. */

const igbinary_type_object_id8   = "\x1a";		/**< Object string id. */
const igbinary_type_object_id16  = "\x1b";		/**< Object string id. */
const igbinary_type_object_id32  = "\x1c";		/**< Object string id. */

const igbinary_type_object_ser8  = "\x1d";		/**< Object serialized data. */
const igbinary_type_object_ser16 = "\x1e";	/**< Object serialized data. */
const igbinary_type_object_ser32 = "\x1f";	/**< Object serialized data. */

const igbinary_type_long64p      = "\x20";			/**< Long 64bit positive. */
const igbinary_type_long64n      = "\x21";			/**< Long 64bit negative. */

const igbinary_type_objref8      = "\x22";			/**< Object reference. */
const igbinary_type_objref16     = "\x23";		/**< Object reference. */
const igbinary_type_objref32     = "\x24";		/**< Object reference. */

const igbinary_type_ref          = "\x25";				/**< Simple reference */

class IgbinaryUnserializeData {
	/** []string - a list of string ids */
	private $_strings = [];
	/** Unserialized arrays/objects/references */
	private $_references = [];
}

abstract class ASTNode {
	public $prefix;

	public static function parse($data, $offset, $prefix, IgbinaryUnserializeData $context) {
		list($data, $newOffset) = static::_parse($data, $offset, $prefix, $context);
		$data->prefix = $prefix;
		return [$data, $newOffset];
	}

	private static function _parse($data, $offset, $prefix, IgbinaryUnserializeData $context);

	public abstract function __toString();
	public function toString($indent = 0) {
		$str = $this->__toString();
		if ($indent > 0) {
			$space = str_repeat(' ', $indent);
			return $space . str_replace("\n", "\n" . $space, $str);
		}
	}
}

abstract class ASTRefNode extends ASTNode {
}

trait NoAdditionalParseData {
	private static function _parse($data, $offset, $prefix, IgbinaryUnserializeData $context) {
		return [new static(), $offset];
	}
}

class NullNode extends ASTNode {
	use NoAdditionalParseData;
	public function __toString() {
		return "null";
	}
}

class FalseNode extends ASTNode {
	use NoAdditionalParseData;
	public function __construct() {
		$this->prefix = $data;
	}
	public function __toString() {
		return "false";
	}
}

class TrueNode extends ASTNode {
	use NoAdditionalParseData;
	public function __toString() {
		return "true";
	}
}

function parse_unsigned_binary_long($data, $offset, $n) {
	$data = 0;
	// TODO: 64-bit overflow check, 32-bit system check, 
	for ($i = 0; $i < $n; $i++) {
		$data = ($data << 8) + ord(substr($data, $offset+$i, 1));
	}
}

class LongNode extends ASTNode {
	public $value;

	public function __toString() {
		return (string)$value;
	}

	public function __construct($value) {
		$this->value = $value;
	}

	private static function _parse($data, $offset, $prefix, IgbinaryUnserializeData $context) {
		switch($prefix) {
		case igbinary_type_long8p:  $n = 1; $negative=false; break;
		case igbinary_type_long8n:  $n = 1; $negative=true;  break;
		case igbinary_type_long16p: $n = 2; $negative=false; break;
		case igbinary_type_long16n: $n = 2; $negative=true; break;
		case igbinary_type_long32p: $n = 4; $negative=false; break;
		case igbinary_type_long32n: $n = 4; $negative=true; break;
		case igbinary_type_long64p: $n = 4; $negative=false; break;
		case igbinary_type_long64n: $n = 4; $negative=true; break;
		default: throw new InvalidArgumentException("Unexpected long prefix " . bin2hex($prefix));
		}
		$data = parse_unsigned_binary_long($data, $offset, $n);
		return new self($negative ? -$data : $data);
	}
}

class ObjectNode extends ASTRefNode {
	public $isRef;
	public $numEntries = 0;
	public $children = [];
		
	private static function _parse($data, $offset, $prefix, IgbinaryUnserializeData $context) {
		$this->isRef = substr($prefix, 0, 1) === igbinary_type_ref;
		if ($this->isRef) {
			$prefix = substr($prefix, 1);
		}
		switch($prefix) {
		case igbinary_type_object8:  $n = 1; break;
		case igbinary_type_object16: $n = 2; break;
		case igbinary_type_object32: $n = 4; break;
		default: throw new InvalidArgumentException("Bad ObjectNode arg");
		}
		$this->numEntries = parse_unsigned_binary_long($data, $offset, $n);
	}
}

class ArrayNode extends ASTRefNode {
	public $isRef;
	public $numEntries = 0;
	public $children = [];
	private static function _parse($data, $offset, $prefix, IgbinaryUnserializeData $context) {
		$this->isRef = substr($prefix, 0, 1) === igbinary_type_ref;
		if ($this->isRef) {
			$prefix = substr($prefix, 1);
		}
		switch($prefix) {
		case igbinary_type_array8:  $n = 1; break;
		case igbinary_type_array16: $n = 2; break;
		case igbinary_type_array32: $n = 4; break;
		default: throw new InvalidArgumentException("Bad ObjectNode arg");
		}
		$this->numEntries = parse_unsigned_binary_long($data, $offset, $n);
	}
}

abstract class CFGRule {
}

abstract class TerminalRule extends CFGRule {
	public function __construct($length, $type) {
	}
}

class CFGNode {
	private $_rules;
	public function addRule($prefix, CFGRule $rule) {
		 $this->rules[$prefix] = $rule;
	}
}

function build_parser() {
	$ZVAL = new CFGNode();
	$ARRAY = new CFGNode();
	$OBJECT = new CFGNode();
	$ENTRY = new CFGNode();
	$ARRAY->addRule(igbinary_type_array8,   new DynamicSizeRule(1, $ENTRY));
	$ARRAY->addRule(igbinary_type_array16,  new DynamicSizeRule(2, $ENTRY));
	$ARRAY->addRule(igbinary_type_array32,  new DynamicSizeRule(4, $ENTRY));
	$OBJECT->addRule(igbinary_type_object8,   new DynamicSizeRule(1, $ENTRY));
	$OBJECT->addRule(igbinary_type_object16,  new DynamicSizeRule(2, $ENTRY));
	$OBJECT->addRule(igbinary_type_object32,  new DynamicSizeRule(4, $ENTRY));
}
