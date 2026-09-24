<?php
/**
 * Tests that keep the values declared in several places from drifting apart.
 * 複数の場所に書いてある宣言の値が、ばらばらにならないようにするテスト。
 *
 * These are not tests of one function. Each guards a mismatch that has happened, or that
 * WordPress accepts without any error: the version constant that stayed at 1.0.2 while the
 * header said 1.0.4, and a text domain that no longer matches the header.
 * 1つの関数のテストではない。実際に起きた、または WordPress がエラーを出さずに受け入れてしまう
 * ずれを止めるためのもの。ヘッダが 1.0.4 なのに定数が 1.0.2 のまま残っていたこと、
 * テキストドメインがヘッダと食い違うことなど。
 *
 * @package etbs-order-note-templates
 */

/**
 * Compares the main file header, the constants, readme.txt and the source files.
 * メインファイルのヘッダ、定数、readme.txt、ソースファイルを突き合わせる。
 */
class Test_Etbs_Ont_Plugin_Header extends WP_UnitTestCase {

	/**
	 * Reads the main file header.
	 * メインファイルのヘッダを読む。
	 *
	 * @return array Header values keyed by the names used below.
	 */
	private function get_header() {
		return get_file_data(
			ETBS_ONT_PLUGIN_FILE,
			array(
				'name'         => 'Plugin Name',
				'version'      => 'Version',
				'requires_php' => 'Requires PHP',
				'requires_wp'  => 'Requires at least',
				'text_domain'  => 'Text Domain',
				'domain_path'  => 'Domain Path',
			)
		);
	}

	/**
	 * Reads readme.txt as text.
	 * readme.txt を文字列として読む。
	 *
	 * @return string Contents of readme.txt.
	 */
	private function get_readme() {
		return (string) file_get_contents( dirname( ETBS_ONT_PLUGIN_FILE ) . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.
	}

	/**
	 * Returns one value from the readme.txt header block, such as "Stable tag".
	 * readme.txt の先頭のヘッダから、Stable tag などの値を1つ返す。
	 *
	 * @param string $key Header name.
	 * @return string The value, or an empty string when the header is missing.
	 */
	private function get_readme_header( $key ) {
		return preg_match( '/^' . preg_quote( $key, '/' ) . ':\s*(.+)$/mi', $this->get_readme(), $matches ) ? trim( $matches[1] ) : '';
	}

	/**
	 * The version is declared in three places, and they must be the same.
	 * 版数は3か所に書いてあり、同じでなければならない。
	 *
	 * @return void
	 */
	public function test_version_is_the_same_everywhere() {
		$header = $this->get_header();

		$test_cases = array(
			array(
				'test_condition_name' => 'ETBS_ONT_VERSION がヘッダの Version と同じ',
				'actual'              => ETBS_ONT_VERSION,
				'expected'            => $header['version'],
			),
			array(
				'test_condition_name' => 'readme.txt の Stable tag がヘッダの Version と同じ',
				'actual'              => $this->get_readme_header( 'Stable tag' ),
				'expected'            => $header['version'],
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}

	/**
	 * Requires PHP is declared in two places (the header and readme.txt), and they must be the same.
	 * Requires PHP は2か所（ヘッダと readme.txt）に書いてあり、同じでなければならない。
	 *
	 * @return void
	 */
	public function test_requires_are_declared_consistently() {
		$header = $this->get_header();

		$test_cases = array(
			array(
				'test_condition_name' => 'ヘッダと readme.txt の Requires PHP が同じ',
				'actual'              => $this->get_readme_header( 'Requires PHP' ),
				'expected'            => $header['requires_php'],
			),
			array(
				'test_condition_name' => 'ヘッダに Requires at least を書いていない（実測した下限が無いため）',
				'actual'              => $header['requires_wp'],
				'expected'            => '',
			),
			array(
				'test_condition_name' => 'readme.txt に Requires at least を書いていない（実測した下限が無いため）',
				'actual'              => $this->get_readme_header( 'Requires at least' ),
				'expected'            => '',
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}

	/**
	 * The header names, and the readme.txt title, must agree.
	 * ヘッダの各名前と、readme.txt の見出しが揃っていなければならない。
	 *
	 * @return void
	 */
	public function test_names_are_declared_consistently() {
		$header = $this->get_header();

		$test_cases = array(
			array(
				'test_condition_name' => 'Text Domain がプラグインのスラッグ（etbs-order-note-templates）と一致している',
				'actual'              => $header['text_domain'],
				'expected'            => 'etbs-order-note-templates',
			),
			array(
				'test_condition_name' => 'Domain Path が /languages',
				'actual'              => $header['domain_path'],
				'expected'            => '/languages',
			),
			array(
				'test_condition_name' => 'メインファイル名が Text Domain に .php を付けたもの',
				'actual'              => basename( ETBS_ONT_PLUGIN_FILE ),
				'expected'            => $header['text_domain'] . '.php',
			),
			array(
				'test_condition_name' => 'readme.txt の見出しが Plugin Name と同じ',
				'actual'              => 1 === preg_match( '/^=== (.+) ===$/m', $this->get_readme(), $matches ) ? $matches[1] : '',
				'expected'            => $header['name'],
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}

	/**
	 * Every translation function in the plugin's PHP files uses the plugin's text domain.
	 * プラグインの PHP ファイルの翻訳関数が、すべてこのプラグインのテキストドメインを使っている。
	 *
	 * @return void
	 */
	public function test_text_domain_is_used_everywhere() {
		$plugin_dir = dirname( ETBS_ONT_PLUGIN_FILE );
		$files      = array_merge( array( ETBS_ONT_PLUGIN_FILE ), glob( $plugin_dir . '/inc/*.php' ) );

		$domains = array();
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.
			// The last string argument of __(), _e(), esc_html__(), esc_attr__() and the like.
			// __()・_e()・esc_html__()・esc_attr__() などの、最後の文字列引数.
			if ( preg_match_all( '/\b(?:__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*\'(?:[^\'\\\\]|\\\\.)*\'\s*,\s*\'([^\']*)\'\s*\)/', $source, $matches ) ) {
				$domains = array_merge( $domains, $matches[1] );
			}
		}

		$this->assertNotEmpty( $domains, '翻訳関数が1つも見つからない（検出の正規表現が壊れていないか）' );
		$this->assertSame( array( 'etbs-order-note-templates' ), array_values( array_unique( $domains ) ), '使っているテキストドメインは1種類だけ' );
	}

	/**
	 * The renamed constants exist, and the old ones (which the predecessor defines) do not come from this plugin.
	 * 改名した定数が存在し、前身が定義する旧名は、このプラグインからは定義されていない。
	 *
	 * @return void
	 */
	public function test_constants_do_not_collide_with_the_predecessor() {
		$test_cases = array(
			array(
				'test_condition_name' => 'ETBS_ONT_VERSION が定義されている',
				'actual'              => defined( 'ETBS_ONT_VERSION' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ETBS_ONT_PLUGIN_FILE がこのメインファイルを指す',
				'actual'              => basename( ETBS_ONT_PLUGIN_FILE ),
				'expected'            => 'etbs-order-note-templates.php',
			),
			array(
				'test_condition_name' => '前身と同名の ORMM_VERSION を定義していない',
				'actual'              => defined( 'ORMM_VERSION' ),
				'expected'            => false,
			),
			array(
				'test_condition_name' => '前身と同名の ORMM_PLUGIN_FILE を定義していない',
				'actual'              => defined( 'ORMM_PLUGIN_FILE' ),
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}
}
