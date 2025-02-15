<?php
/**
 * Tests for class Import.
 *
 * @package Genesis\CustomBlocks
 */

use Genesis\CustomBlocks\Admin\Import;
use Brain\Monkey;

/**
 * Tests for class Import.
 */
class TestImport extends AbstractTemplate {

	/**
	 * Instance of Import.
	 *
	 * @var Import
	 */
	public $instance;

	/**
	 * The location of the fixture import file with valid JSON.
	 *
	 * @var string
	 */
	public $import_file_valid_json;

	/**
	 * The location of the fixture import file with invalid JSON.
	 *
	 * @var string
	 */
	public $import_file_invalid_json;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function set_up() {
		parent::set_up();
		Monkey\setUp();
		$this->instance                 = new Import();
		$this->import_file_valid_json   = dirname( __DIR__ ) . '/Fixtures/MockImportValidFormat.txt';
		$this->import_file_invalid_json = dirname( __DIR__ ) . '/Fixtures/MockImportInvalidFormat.txt';
		$this->instance->set_plugin( genesis_custom_blocks() );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	public function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Test register_hooks.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::register_hooks()
	 */
	public function test_register_hooks() {
		$this->instance->register_hooks();
		$this->assertEquals( 10, has_filter( 'admin_init', [ $this->instance, 'register_importer' ] ) );
	}

	/**
	 * Test register_importer.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::register_importer()
	 */
	public function test_register_importer() {
		global $wp_importers;

		$this->instance->register_importer();
		$this->assertEquals(
			[
				'Genesis Custom Blocks',
				'Import custom blocks created with Genesis Custom Blocks.',
				[ $this->instance, 'render_page' ],
			],
			$wp_importers[ $this->instance->slug ]
		);
	}


	/**
	 * Test render_page.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_page()
	 */
	public function test_render_page() {
		$page_header_text = 'Import Genesis Custom Blocks';
		$welcome_text     = 'Welcome! This importer processes Genesis Custom Blocks JSON files, adding custom blocks to this site.';

		Monkey\Functions\expect( 'filter_input' )
			->once()
			->with(
				INPUT_GET,
				'step',
				FILTER_SANITIZE_NUMBER_INT
			)
			->andReturn( 0 );

		ob_start();
		$this->instance->render_page();
		$output = ob_get_clean();

		// If filter_input() returns 0, it this should output the page header and welcome text.
		$this->assertStringContainsString( $page_header_text, $output );
		$this->assertStringContainsString( $welcome_text, $output );

		Monkey\Functions\expect( 'filter_input' )
			->once()
			->with(
				INPUT_GET,
				'step',
				FILTER_SANITIZE_NUMBER_INT
			)
			->andReturn( null );

		ob_start();
		$this->instance->render_page();
		$output = ob_get_clean();

		// If filter_input() returns null, it should also output the page header and welcome text.
		$this->assertStringContainsString( $page_header_text, $output );
		$this->assertStringContainsString( $welcome_text, $output );

		Monkey\Functions\expect( 'filter_input' )
			->once()
			->with(
				INPUT_GET,
				'step',
				FILTER_SANITIZE_NUMBER_INT
			)
			->andReturn( 1 );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'import-upload' );
		$error_uploading_file = 'Sorry, there was an error uploading the file.';
		ob_start();
		$this->instance->render_page();
		$output = ob_get_clean();

		// If filter_input() returns 1, it should not have welcome text, but there should be an 'error uploading' message.
		$this->assertStringContainsString( $page_header_text, $output );
		$this->assertStringNotContainsString( $welcome_text, $output );
		$this->assertStringContainsString( $error_uploading_file, $output );

		$file             = [ 'file' => 'nonexistent-file.xml' ];
		$tmp_name         = $this->import_file_invalid_json;
		$files_import     = array_merge(
			$file,
			[
				'name'     => 'foo',
				'tmp_name' => $tmp_name,
				'size'     => 10000,
			]
		);
		$_FILES['import'] = $files_import;
		add_filter(
			'wp_handle_upload',
			function( $upload ) use ( $file ) {
				unset( $upload );
				return array_merge(
					$file,
					[
						'url'  => 'https://example.com/foo',
						'type' => 'text/plain',
					]
				);
			}
		);

		Monkey\Functions\expect( 'filter_input' )
			->twice()
			->with(
				INPUT_GET,
				'step',
				FILTER_SANITIZE_NUMBER_INT
			)
			->andReturn( 1 );

		Monkey\Functions\expect( 'is_uploaded_file' )
			->once()
			->with( $tmp_name )
			->andReturn( true );

		/**
		 * Overrides the function to handle upload errors.
		 *
		 * @param array  $file    The file that was uploaded.
		 * @param string $message The message.
		 * @return array The error message.
		 */
		function wp_handle_upload_error( $file, $message ) {
			unset( $file );
			return [ 'error' => $message ];
		}

		ob_start();
		$this->instance->render_page();
		$output = ob_get_clean();

		// If filter_input() returns 1 and the file does not exist, it should not have welcome text, but there should be an 'error uploading' message.
		$this->assertStringContainsString( $error_uploading_file, $output );
		$this->assertStringContainsString( $page_header_text, $output );
		$this->assertStringNotContainsString( $welcome_text, $output );

		// The file is now a real file.
		$file             = [ 'file' => $this->import_file_valid_json ];
		$tmp_name         = $this->import_file_valid_json;
		$files_import     = array_merge(
			$file,
			[
				'name'     => 'MockImportValidFormat',
				'tmp_name' => $tmp_name,
				'size'     => 29,
			]
		);
		$_FILES['import'] = $files_import;

		remove_all_filters( 'wp_handle_upload' );
		add_filter(
			'wp_handle_upload',
			function() use ( $file ) {
				return array_merge(
					$file,
					[
						'url'  => 'https://example.com/foo',
						'type' => 'text/plain',
					]
				);
			}
		);

		Monkey\Functions\expect( 'is_uploaded_file' )
			->once()
			->with( $tmp_name )
			->andReturn( true );

		Monkey\Functions\expect( 'move_uploaded_file' )
			->once()
			->andReturn( true );

		Monkey\Functions\expect( 'chmod' )
			->once()
			->andReturn( true );

		ob_start();
		$this->instance->render_page();
		$output = ob_get_clean();

		// Now that this has a real file, it should not output the 'error uploading' message.
		$this->assertStringNotContainsString( $error_uploading_file, $output );
		$this->assertStringContainsString( $page_header_text, $output );
		$this->assertStringNotContainsString( $welcome_text, $output );
	}

	/**
	 * Test render_page_header.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_page_header()
	 */
	public function test_render_page_header() {
		ob_start();
		$this->instance->render_page_header();

		$this->assertStringContainsString( '<h2>Import Genesis Custom Blocks</h2>', ob_get_clean() );
	}

	/**
	 * Test render_welcome.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_welcome()
	 */
	public function test_render_welcome() {
		ob_start();
		$this->instance->render_welcome();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p>Welcome! This importer processes Genesis Custom Blocks JSON files, adding custom blocks to this site.</p>', $output );
		$this->assertStringContainsString( '<label for="upload">Choose a file from your computer:</label>', $output );
		$this->assertStringContainsString( 'This JSON file should come from the export link or bulk action in the', $output );
	}

	/**
	 * Test render_import_success.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_import_success()
	 */
	public function test_render_import_success() {
		$title = 'Example Title';
		ob_start();
		$this->instance->render_import_success( $title );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p>Successfully imported <strong>', $output );
		$this->assertStringContainsString( $title, $output );
	}

	/**
	 * Test render_import_error.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_import_error()
	 */
	public function test_render_import_error() {
		$title = 'Baz Title';
		$error = 'Example Error';
		ob_start();
		$this->instance->render_import_error( $title, $error );
		$output = ob_get_clean();

		$this->assertStringContainsString( $title, $output );
		$this->assertStringContainsString( $error, $output );

		$disallowed = '<script type="text/javascript;">do_evil();</script>';
		ob_start();
		$this->instance->render_import_error( $title, $disallowed );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( $disallowed, $output );
	}

	/**
	 * Test render_done.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_done()
	 */
	public function test_render_done() {
		ob_start();
		$this->instance->render_done();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p>All done!</p>', $output );
	}

	/**
	 * Test render_choose_blocks.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::render_choose_blocks()
	 */
	public function test_render_choose_blocks() {
		$name   = 'block-name';
		$title  = 'Example Block Title';
		$blocks = [
			"genesis-custom-blocks/$name" => [
				'name'  => $name,
				'title' => $title,
			],
		];
		ob_start();
		$this->instance->render_choose_blocks( $blocks );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p>Please select the blocks to import:</p>', $output );
		$this->assertStringContainsString( 'name="genesis-custom-blocks/' . $name . '"', $output );
		$this->assertStringContainsString( 'id="genesis-custom-blocks/' . $name . '"', $output );
		$this->assertStringContainsString( '<strong>' . $title . '</strong>', $output );
	}

	/**
	 * Test validate_upload.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::validate_upload()
	 */
	public function test_validate_upload() {
		$error           = 'This is an invalid file';
		$file_with_error = compact( 'error' );

		ob_start();
		$this->assertFalse( $this->instance->validate_upload( $file_with_error ) );
		$output = ob_get_clean();

		// If there's an 'error' value in the argument, this should output it.
		$this->assertStringContainsString( $error, $output );
		$this->assertStringContainsString( 'Sorry, there was an error uploading the file.', $output );

		$nonexistent_file = 'does-not-exist.xml';

		ob_start();
		$this->assertFalse( $this->instance->validate_upload( [ 'file' => $nonexistent_file ] ) );
		$output = ob_get_clean();

		// If the file doesn't exist, this should have a message that reflects that.
		$this->assertStringContainsString( $nonexistent_file, $output );
		$this->assertStringContainsString( '<p><strong>Sorry, there was an error uploading the file.</strong>', $output );

		ob_start();
		$this->assertFalse( $this->instance->validate_upload( [ 'file' => $this->import_file_invalid_json ] ) );
		$output = ob_get_clean();

		// If the file has invalid JSON, the message should reflect that.
		$this->assertStringContainsString( '<p><strong>Sorry, there was an error processing the file.</strong></p><p>Invalid JSON.</p>', $output );

		ob_start();
		$this->assertTrue( $this->instance->validate_upload( [ 'file' => $this->import_file_valid_json ] ) );
		$output = ob_get_clean();

		// If the file exists and has valid JSON, it shouldn't output a message.
		$this->assertEmpty( $output );
	}

	/**
	 * Test import_blocks.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::import_blocks()
	 */
	public function test_import_blocks() {
		$name             = 'block-name';
		$title            = 'Example Block Title';
		$success_message  = '<p>Successfully imported';
		$blocks_to_import = [
			"genesis-custom-blocks/$name" => [
				'title' => $title,
			],
		];

		ob_start();
		$this->instance->import_blocks( $blocks_to_import );
		$output      = ob_get_clean();
		$block_query = new WP_Query( [ 'post_type' => 'genesis_custom_block' ] );

		// When the 'name' isn't passed to the method, it shouldn't import any block, but should still have the 'All Done!' message.
		$this->assertEmpty( $block_query->found_posts );
		$this->assertStringContainsString( 'All done!', $output );
		$this->assertStringNotContainsString( $success_message, $output );

		$blocks_to_import = [
			"genesis-custom-blocks/$name" => [
				'name'  => $name,
				'title' => $title,
			],
		];

		ob_start();
		$this->instance->import_blocks( $blocks_to_import );
		$output = ob_get_clean();

		// When the 'name' and 'title are passed to the method, it should import the block and have the 'success' message.
		$this->assertStringContainsString( $success_message, $output );
		$this->assertStringContainsString( $title, $output );

		$block_query     = new WP_Query( [ 'post_type' => 'genesis_custom_block' ] );
		$block           = reset( $block_query->posts );
		$decoded_block   = json_decode( $block->post_content );
		$full_block_name = 'genesis-custom-blocks/' . $name;
		$block_data      = $decoded_block->$full_block_name;

		$this->assertEquals( $name, $block_data->name );
		$this->assertEquals( $title, $block_data->title );
	}

	/**
	 * Test block_exists.
	 *
	 * @covers \Genesis\CustomBlocks\Admin\Import::block_exists()
	 */
	public function test_block_exists() {
		$block_namespace = 'genesis-custom-blocks/block-name';

		$this->assertFalse( $this->invoke_protected_method( 'block_exists', [ $block_namespace ] ) );

		register_block_type(
			$block_namespace,
			[
				'render_callback' => function() {},
			]
		);

		$this->assertTrue( $this->invoke_protected_method( 'block_exists', [ $block_namespace ] ) );
	}
}
