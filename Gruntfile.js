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
					'!README.md',
					'!composer.lock',
					'!composer.json'
				],
				dest: 'release/<%= pkg.version %>/'
			}
		},
		compress: {
			main: {
				options: {
					mode: 'zip',
					archive: './release/<%= pkg.name %>.zip'
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
