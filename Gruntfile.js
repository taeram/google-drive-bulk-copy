/**
 * Gruntfile.js
 *
 * This file is used to deploy changes to this site.
 */

module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        exec: {
            composer_install: "composer install --optimize-autoloader -vv",
            logs_path_writable: "sudo chown $USER -R ./logs",
            npm_install: "npm install",
            sudo_check: "TMP_FILE=$( mktemp ) && sudo chown $USER $TMP_FILE && rm -f $TMP_FILE"
        }
    });

    grunt.loadNpmTasks('grunt-exec');

    // default task
    grunt.registerTask('default', [
        'exec:sudo_check',
        'exec:composer_install',
        'exec:npm_install',
        'exec:logs_path_writable'
    ]);
};
