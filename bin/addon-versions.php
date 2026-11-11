<?php
/**
 * Addon release planning and validation, without WordPress dependencies.
 *
 * @package ChurchTools_Suite
 */

// This standalone CLI uses native process/file operations and emits plain terminal output.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.Security.EscapeOutput

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

/**
 * Run Git without shell interpolation.
 *
 * @param array<int,string> $arguments Git arguments.
 * @return string Standard output.
 * @throws RuntimeException When Git fails.
 */
function cts_release_git( array $arguments ): string {
	$command = array_merge( array( 'git' ), $arguments );
	$process = proc_open(
		$command,
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Cannot start Git.' );
	}
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	if ( proc_close( $process ) !== 0 ) {
		throw new RuntimeException( 'Git failed: ' . trim( $error ) );
	}
	return rtrim( $output, "\r\n" );
}

/**
 * Read the authoritative plugin header, accepting legacy input for migration.
 *
 * @param string $source Plugin source.
 * @return string Version.
 * @throws RuntimeException When the header is missing or ambiguous.
 */
function cts_release_header( string $source ): string {
	if ( preg_match_all( '/^ \* Version:\s*([0-9]+(?:\.[0-9]+){2,3})\s*$/m', $source, $matches ) !== 1 ) {
		throw new RuntimeException( 'Expected exactly one addon Version header.' );
	}
	return $matches[1][0];
}

/**
 * Validate the entire addon release plan, optionally applying it.
 *
 * @param array<int,string> $argv CLI arguments.
 * @throws RuntimeException When release inputs are invalid.
 */
function cts_addon_release_main( array $argv ): void {
	chdir( dirname( __DIR__ ) );
	$mode = $argv[1] ?? 'check';
	if ( ! in_array( $mode, array( 'check', 'apply', 'verify' ), true ) ) {
		throw new RuntimeException( 'Usage: addon-versions.php check|apply|verify [base-release-tag]' );
	}
	$base = $argv[2] ?? cts_release_git( array( 'describe', '--tags', '--abbrev=0', '--match', '[0-9]*', '--match', 'v[0-9]*', 'verify' === $mode ? 'HEAD^' : 'HEAD' ) );
	if ( ! preg_match( '/^v?[0-9]+(?:\.[0-9]+){2,3}(?:-[0-9]{2})?$/D', $base ) ) {
		throw new RuntimeException( 'Expected a release tag as comparison base.' );
	}
	cts_release_git( array( 'merge-base', '--is-ancestor', $base, 'HEAD' ) );
	$pending = json_decode( file_get_contents( 'release-pending.json' ), false, 32, JSON_THROW_ON_ERROR );
	if ( ! $pending instanceof stdClass ) {
		throw new RuntimeException( 'release-pending.json must be a JSON object.' );
	}
	$pending = (array) $pending;
	$levels  = array( 'patch', 'minor', 'major' );
	$addons  = array();
	foreach ( glob( 'addons/*', GLOB_ONLYDIR ) as $directory ) {
		$slug = basename( $directory );
		if ( is_file( "$directory/$slug.php" ) ) {
			$addons[ $slug ] = $directory;
		}
	}
	foreach ( $pending as $slug => $level ) {
		if ( ! isset( $addons[ $slug ] ) || ! in_array( $level, $levels, true ) ) {
			throw new RuntimeException( "Invalid pending addon or bump: $slug" );
		}
	}
	if ( 'verify' === $mode && $pending ) {
		throw new RuntimeException( 'Release still contains pending addon bumps. Use make release.' );
	}
	$writes = array();
	foreach ( $addons as $slug => $directory ) {
		$file    = "$directory/$slug.php";
		$source  = file_get_contents( $file );
		$old     = cts_release_header( $source );
		$changed = cts_release_git( array( 'diff', '--name-only', $base, '--', $directory ) ) !== ''
			|| cts_release_git( array( 'ls-files', '--others', '--exclude-standard', '--', $directory ) ) !== '';
		if ( 'verify' !== $mode && $changed && ! isset( $pending[ $slug ] ) ) {
			throw new RuntimeException( "Changed addon $slug needs an entry in release-pending.json (base: $base)." );
		}
		$version = $old;
		if ( isset( $pending[ $slug ] ) ) {
			$parts = array_map( 'intval', explode( '.', $old ) );
			$index = array(
				'major' => 0,
				'minor' => 1,
				'patch' => 2,
			)[ $pending[ $slug ] ];
			$parts = array_slice( $parts, 0, 3 );
			++$parts[ $index ];
			for ( $i = $index + 1; $i < 3; ++$i ) {
				$parts[ $i ] = 0;
			}
			$version  = implode( '.', $parts );
			$source   = preg_replace_callback( '/^( \* Version:\s*)[0-9.]+/m', static fn( array $m ): string => $m[1] . $version, $source );
			$constant = "/^(define\( 'CTS_[A-Z_]+_VERSION', ')[0-9.]+(' \);)$/m";
			if ( preg_match_all( $constant, $source ) !== 1 ) {
				throw new RuntimeException( "Expected exactly one version constant in $file." );
			}
			$source          = preg_replace_callback( $constant, static fn( array $m ): string => $m[1] . $version . $m[2], $source );
			$writes[ $file ] = $source;
			echo "$slug: $old -> $version ({$pending[$slug]})\n";
		}
		if ( ! preg_match( '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version )
				|| ! preg_match( "/^define\( 'CTS_[A-Z_]+_VERSION', '" . preg_quote( $version, '/' ) . "' \);$/m", $source ) ) {
			throw new RuntimeException( "Invalid or inconsistent addon version: $slug" );
		}
		$existed = cts_release_git( array( 'ls-tree', '--name-only', $base, '--', $file ) ) !== '';
		if ( $existed && ( $changed || isset( $pending[ $slug ] ) ) ) {
			$previous = cts_release_header( cts_release_git( array( 'show', "$base:$file" ) ) );
			if ( ! version_compare( $version, $previous, '>' ) ) {
				throw new RuntimeException( "Changed addon $slug must have a higher version than $previous." );
			}
		}
		$readme = "$directory/readme.txt";
		if ( is_file( $readme ) ) {
			$text = file_get_contents( $readme );
			if ( isset( $pending[ $slug ] ) ) {
				$writes[ $readme ] = preg_replace_callback( '/^(Stable tag:\s*)[^\r\n]+/mi', static fn( array $m ): string => $m[1] . $version, $text );
			} elseif ( preg_match( '/^Stable tag:\s*([^\r\n]+)/mi', $text, $match ) && trim( $match[1] ) !== $version ) {
				throw new RuntimeException( "Stable tag mismatch: $readme" );
			}
		}
	}
	// Validate the complete plan before changing any file.
	if ( 'apply' === $mode ) {
		$writes['release-pending.json'] = "{}\n";
		foreach ( $writes as $file => $contents ) {
			if ( file_put_contents( $file, $contents ) !== strlen( $contents ) ) {
				throw new RuntimeException( "Cannot write $file. Restore the working tree before retrying." );
			}
		}
	}
	echo "Addon release $mode OK (base: $base).\n";
}

try {
	cts_addon_release_main( $argv );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
