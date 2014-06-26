<?phpphp
/**
 * Tests for Node
 *
 * @author Pontus Persson <pom@spotify.com>
 */

use \Asenine\Node;

class ActiveNode extends Node
{
	public function __construct($a, $b)
	{
		$this->firstname = &$this->addAttr('firstname', $a)->value;
		$L = $this->addAttr('lastname', $b);
		$this->lastname = &$L->value;
		$this->addData('meta', $L);
	}
}

class NodeTest extends PHPUnit_Framework_TestCase {

	function setUp() { }

	function tearDown() { }

	function testResponsiveValues()
	{
		$Node = new ActiveNode('walter', 'white');
		$this->assertEquals('<node firstname="walter" lastname="white" data-meta="white"></node>', (string)$Node);

		$Node->firstname = 'clark';
		$Node->lastname = 'kent';
		$this->assertEquals('<node firstname="clark" lastname="kent" data-meta="kent"></node>', (string)$Node);

		$Node->addClass('super');
		$this->assertEquals('<node firstname="clark" lastname="kent" data-meta="kent" class="super"></node>', (string)$Node);

		$Node->firstname = 'Phyllis & Gene';
		$Node->lastname = 'Jony "M" Jackson';
		$this->assertEquals('<node firstname="Phyllis &amp; Gene" lastname="Jony &quot;M&quot; Jackson" data-meta="Jony &quot;M&quot; Jackson" class="super"></node>', (string)$Node);
	}
}
