const copy = require('copy');
const fs = require('fs');

console.log('');
console.log('---------------------------------------');
console.log('copy the files to the correct directory');
console.log('---------------------------------------');

if (fs.existsSync('web/assets/')) {
    fs.rmSync('web/assets/', {
        recursive: true
    });
}
fs.mkdirSync('web/assets');

copy('resources/wtj/**', 'web/assets/wtj', function(err, file) {
    // exposes the vinyl `file` created when the file is copied
});

copy('resources/images/*', 'web/assets/images', function(err, file) {
    // exposes the vinyl `file` created when the file is copied
});

fs.mkdirSync('web/assets/js');
fs.copyFileSync('resources/js/diff-match-patch.js', 'web/assets/js/diff-match-patch.js');
