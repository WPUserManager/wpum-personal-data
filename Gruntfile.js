module.exports = function( grunt ) {

	// load all grunt tasks in package.json matching the `grunt-*` pattern
	require( 'load-grunt-tasks' )( grunt );

	// Project configuration
	grunt.initConfig( {
		pkg:    grunt.file.readJSON( 'package.json' ),
		clean: {
			main: ['release']
		},
		copy: {
			// Copy the plugin to a versioned release directory
			main: {
				src:  [
					'**',
					'!node_modules/**',
					'!release/**',
					'!.git/**',
					'!.sass-cache/**',
					'!assets/css/src/**',
					'!assets/css/**/*.map',
					'!assets/js/src/**',
					'!img/src/**',
					'!Gruntfile.js',
					'!package.json',
					'!.gitignore',
					'!.gitmodules',
					'!yarn.lock',
					'!package-lock.json',
					'!.yarnrc',
					'!README.md',
					'!composer.lock',
					'!composer.json',
					'!.github/**',
					'!release.json',
					'!playwright.config.ts',
					'!tsconfig.json',
					'!.wp-env.json',
					'!.wp-env.override.json',
					'!tests/**',
					'!test-results/**',
					'!playwright-report/**',
					'!phpcs.xml',
					'!phpstan.neon.dist',
					'!phpstan-baseline.neon',
					'!phpstan-stubs.php'
				],
				dest: 'release/<%= pkg.version %>/'
			}
		},
		compress: {
			main: {
				options: {
					mode: 'zip',
					archive: './release/<%= pkg.name %>.<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'release/<%= pkg.version %>/',
				src: ['**/*'],
				dest: '<%= pkg.name %>/'
			}
		},
		makepot: {
			target: {
				options: {
					exclude: [
						'assets/.*', 'images/.*', 'node_modules/.*', 'tests/.*', 'release/.*', 'build/.*'
					],
					domainPath: '/languages',
					mainFile: '<%= pkg.name %>.php',
					potFilename: '<%= pkg.name %>.pot',
					potHeaders: {
						poedit: true,                 // Includes common Poedit headers.
						'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
					},
					type: 'wp-plugin'
				}
			}
		},
	} );

	grunt.registerTask( 'do_pot', ['makepot'] );
	grunt.registerTask( 'build', ['clean', 'copy', 'compress'] );
};
