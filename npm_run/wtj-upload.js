const Client = require('ssh2-sftp-client');
const fs = require('fs');
require('dotenv').config();

const sftp = new Client();
const sftpSSHKey = fs.readFileSync(process.env.JS_SFTP_KEYPATH);

sftp.connect({
    host: process.env.JS_SFTP_HOST,
    port: process.env.JS_SFTP_PORT,
    username: process.env.JS_SFTP_USERNAME,
    privateKey: sftpSSHKey,
    passphrase: process.env.JS_SFTP_PASSPHRASE,
}).then(() => {
    return sftp.list(process.env.JS_SFTP_MAINDIR);
}).then(data => {
    console.log(data);
}).then(() => {
    // delete existing folder
    return sftp.rmdir(`${process.env.JS_SFTP_MAINDIR}/web/assets`, true)
}).then(() => {
    return sftp.uploadDir('./web/assets', `${process.env.JS_SFTP_MAINDIR}/web/assets`);
}).then(data => {
    console.log(data);
}).then(() => {
    return sftp.end();
}).catch(err => {
    console.log(err, 'catch error');
});
