const fs = require('fs');
const axios = require('axios');
const jsdom = require("jsdom");

const wtjFolder = './resources/wtj';
const wtjFolderView = './resources/views/original';
const wtjTwig = `${wtjFolderView}/webtigerjython.twig`;
const wtjAssets = '/assets/wtj';
const wtjUrl = 'https://webtigerjython.ethz.ch/'

async function prepareFolder() {
    if (fs.existsSync(wtjFolder)) {
        fs.rmSync(wtjFolder, { recursive: true, force: true });
        console.log('folder exists. remove folder ' + wtjFolder);
    }

    if (fs.existsSync(wtjFolderView)) {
        fs.rmSync(wtjFolderView, { recursive: true, force: true });
        console.log('folder exists. remove folder ' + wtjFolderView);
    }

    fs.mkdirSync(wtjFolder);
    fs.mkdirSync(wtjFolderView);
    console.log('created main folder');
}

function extractCSS(htmlString) {
    // const parser = new DOMParser();
    // const doc = parser.parseFromString(htmlString, 'text/html');
    const dom = new jsdom.JSDOM(htmlString);

    const cssLinks = [];
    const linkElements = dom.window.document.getElementsByTagName('link');

    for (let i = 0; i < linkElements.length; i++) {
        const linkElement = linkElements[i];
        const relAttribute = linkElement.getAttribute('rel');

        if (relAttribute === 'stylesheet') {
            const hrefAttribute = linkElement.getAttribute('href');
            cssLinks.push(hrefAttribute);

            if (!hrefAttribute.startsWith('http')) {
                const hrefAttributeNew = wtjAssets + '/' + hrefAttribute;
                linkElement.setAttribute('href', hrefAttributeNew);
            }
        }
    }
    return {
        'links': cssLinks,
        'html': dom.window.document.documentElement.outerHTML
    };
}

function extractSrcTags(htmlString, tagName) {
    const dom = new jsdom.JSDOM(htmlString);

    const srcLinks = [];
    const srcElements = dom.window.document.getElementsByTagName(tagName);

    for (let i = 0; i < srcElements.length; i++) {
        const srcElement = srcElements[i];
        const srcAttribute = srcElement.getAttribute('src');

        if (srcAttribute) {
            srcLinks.push(srcAttribute);

            if (!srcAttribute.startsWith('http')) {
                const srcAttributeNew = wtjAssets + '/' + srcAttribute;
                srcElement.setAttribute('src', srcAttributeNew);
            }
        }
    }
    return {
        'links': srcLinks,
        'html': dom.window.document.documentElement.outerHTML
    };
}

async function downloadFiles(fileList, returnFile = false) {
    const brokenLinks = [];
    for (let i = 0; i < fileList.length; i++) {
        const fileName = fileList[i];

        if (fileName.startsWith('http')) {
            continue;
        }

        if (fileName === 'javascripts/analytics/tracking.datacollector.js') {
            brokenLinks.push(fileName);
            continue;
        }

        const url = `${wtjUrl}${fileName}`;
        const filePath = `${wtjFolder}/${fileName}`;
        const folders = fileName.split("/");

        let currentPath = `${wtjFolder}`;

        for (let i = 0; i < folders.length - 1; i++) {
            const folder = folders[i];
            currentPath += `/${folder}`;
            if (!fs.existsSync(currentPath)) {
                fs.mkdirSync(currentPath);
                console.log(`created folder: ${currentPath}`);
            }
        }

        try {
            const response = await axios.get(url, { responseType: 'stream' });
            const writer = fs.createWriteStream(filePath);

            await new Promise((resolve, reject) => {
                response.data.pipe(writer);
                writer.on('finish', resolve);
                writer.on('error', reject);
            });

            console.log(`File downloaded: ${fileName}`);
        } catch (error) {
            if (error.response.status) {
                brokenLinks.push(fileName);
                console.error(`Download not possible, file ${url} does not exist`);
            } else {
                console.error(`Failed to download file: ${url} to ${filePath}`, error.response.status, error);
            }
        }
    }

    if (returnFile) {
        return fileList[0];
    }

    return brokenLinks;
}

function replaceStringInFile(filePath, searchString, replacementString) {
    try {
        const data = fs.readFileSync(filePath, 'utf8');

        const modifiedContent = data.replace(searchString, replacementString);

        fs.writeFileSync(filePath, modifiedContent, 'utf8');

        console.log(`File ${filePath} modified successfully!`);
    } catch (err) {
        console.error('Error modifying file:', err);
    }
}

// now: start to crawl webtigerjython

(async() => {
    // prepare all the folders
    await prepareFolder();

    //usage
    await axios.get(wtjUrl, {responseType: 'document'}).then((response) => {
        if (response.status !== 200) {
            console.log('-------------------');
            console.log('webseite ' + wtjUrl + ' ist nicht erreichbar.');
            process.exit(1);
        }

        let htmlWTJ = response.data;

        // CSS-Files
        const cssExtraction = extractCSS(htmlWTJ);
        htmlWTJ = cssExtraction.html;

        // Javascript-Files
        const jsExtraction = extractSrcTags(htmlWTJ, 'script');
        htmlWTJ = jsExtraction.html;

        // Image-Files
        const imgExtraction = extractSrcTags(htmlWTJ, 'img');
        htmlWTJ = imgExtraction.html;

        (async() => {
            console.log(cssExtraction.links);

            await downloadFiles(cssExtraction.links).then(brokenCSS => {
                console.log("css downloaded");
                console.log(brokenCSS);
                // todo: remove brokenCSS if there is something broken...
            });
        })().then(async() => {
            console.log(jsExtraction.links);
            await downloadFiles(jsExtraction.links).then(brokenJS => {
                console.log("js downloaded");
                console.log(brokenJS);

                if (brokenJS.length) {
                    for (let i = 0; i < brokenJS.length; i++) {
                        const broken = brokenJS[i];
                        let brokenHTML = `<script type="text/javascript" src="/assets/wtj/${broken}" charset="utf-8"></script>`
                        htmlWTJ = htmlWTJ.replace(brokenHTML, '');
                        brokenHTML = `<script src="/assets/wtj/${broken}"></script>`
                        htmlWTJ = htmlWTJ.replace(brokenHTML, '');
                    }
                }

                // remove analytics
                const regexAnalytics = /<!-- Access statistics -->[\s\S]*?<\/script>/g;
                htmlWTJ = htmlWTJ.replace(regexAnalytics, '');
            });
        }).then(async() => {
            console.log(imgExtraction.links);
            await downloadFiles(imgExtraction.links).then(brokenImages => {
                console.log("images downloaded");
                console.log(brokenImages);

                if (brokenImages.length) {
                    // todo: remove broken images
                }
                // console.log(htmlWTJ);
            });
        }).then(async() => {
            // Additional Files: Files referenced by other files in some code files (like css)
            const additionalFiles = [
                {
                    'file': '/img/grips.png',
                    'origin': '/stylesheets/content.css',
                    'replacement': '/img/grips.png'
                },
                {
                    'file': '/javascripts/ace/theme-crimson_editor.js',
                    'origin': null
                },
                {
                    'file': '/javascripts/ace/mode-python2.js',
                    'origin': null
                },
                {
                    'file': '/javascripts/ace/mode-python3.js',
                    'origin': null
                },
                {
                    'file': '/html/debugger-pane.html',
                    'origin': null
                },
                {
                    'file': '/html/info.html',
                    'origin': null
                },
                {
                    'file': '/stylesheets/info.css',
                    'origin': null
                },
                {
                    'file': '/img/abz-logo.png',
                    'origin': null
                },
                {
                    'file': '/img/minimize.png',
                    'origin': null
                },
                {
                    'file': '/img/ethz-logo.svg',
                    'origin': null
                }
            ];
            const additionalLinks = [];
            for (let i = 0; i < additionalFiles.length; i++) {
                additionalLinks.push(additionalFiles[i]['file']);
            }

            await downloadFiles(additionalLinks, true).then((file) => {
                for (let i = 0; i < additionalFiles.length; i++) {
                    const additionalFile = additionalFiles[i];
                    if (additionalFile['file'] === file) {
                        if (additionalFile['origin'] === false) {
                            continue;
                        }
                        console.log(`replace in file ${wtjFolder}${additionalFile['origin']}`);
                        let oldLinkText = `${additionalFile['file']}`;
                        const newLinkText = `${additionalFile['replacement']}`;
                        replaceStringInFile(`${wtjFolder}${additionalFile['origin']}`, oldLinkText, newLinkText);
                    }
                }
            });
        }).then(() => {
            // replace some parts of some of the files
            // (1) path to language-images
            let oldLinkText = 'document.getElementById("language-image").src="img';
            let newLinkText = `document.getElementById("language-image").src="${wtjAssets}/img`;
            replaceStringInFile(`${wtjFolder}/javascripts/webtigerjython/initialization-compiled.js`, oldLinkText, newLinkText);

            // (2) path to html-info-pane
            oldLinkText = 'd.src="html/';
            newLinkText = `d.src="${wtjAssets}/html/`;
            replaceStringInFile(`${wtjFolder}/javascripts/webtigerjython/initialization-compiled.js`, oldLinkText, newLinkText);

            // (3) path to html-debugger-pane
            oldLinkText = '$.get("html/debugger-pane.html';
            newLinkText = `$.get("${wtjAssets}/html/debugger-pane.html`;
            replaceStringInFile(`${wtjFolder}/javascripts/webtigerjython/debuggerView-compiled.js`, oldLinkText, newLinkText);

            // (4) fullscreen
            oldLinkText = '"img/expand.png"';
            newLinkText = `"${wtjAssets}/img/expand.png"`;
            replaceStringInFile(`${wtjFolder}/javascripts/webtigerjython/initialization-compiled.js`, oldLinkText, newLinkText);
            oldLinkText = '"img/minimize.png"';
            newLinkText = `"${wtjAssets}/img/minimize.png"`;
            replaceStringInFile(`${wtjFolder}/javascripts/webtigerjython/initialization-compiled.js`, oldLinkText, newLinkText);

            // (5) info-box
            oldLinkText = '<h3 data-translate="info-turtle-functions-title"><strong>';
            newLinkText = '<h3><strong>Cloud-Adaption von Beat Temperli</strong></h3>';
            newLinkText += '<p>x.0 </p><p>Speichern & Teilen</p><br>';
            newLinkText += '<p>x.1 </p><p>Gespeicherte Programme überarbeiten & Änderungen markieren</p><br><br>';
            newLinkText += '<p><i>Diese Adaption ist mit der Erlaubnis vom TigerJython-Team entstanden.</i></p>';
            newLinkText += '<h3 data-translate="info-turtle-functions-title"><strong>';
            replaceStringInFile(`${wtjFolder}/html/info.html`, oldLinkText, newLinkText);

            // (6) info-box 2
            oldLinkText = '<h3 data-translate="info-features"><strong>Features</strong></h3>';
            newLinkText = '<h3><strong>Features von WebTigerJython</strong></h3>';
            replaceStringInFile(`${wtjFolder}/html/info.html`, oldLinkText, newLinkText);

            // (6) info-box 3
            oldLinkText = '<h3><strong>Credits</strong></h3>';
            newLinkText = '<br><p>Rückmeldungen und Wünsche zur Cloud-Adaption bitte via <a target="_blank" href="https://github.com/btemperli/webtigerjython-cloud/issues">Github-Issues</a> mitteilen.</p>';
            newLinkText += '<h3><strong>Credits</strong></h3>';
            replaceStringInFile(`${wtjFolder}/html/info.html`, oldLinkText, newLinkText);

            // (7) info-box 4
            oldLinkText = 'entwickelt. Die Benutzung der Applikation';
            newLinkText = 'entwickelt, die Cloud-Erweiterung stammt von Beat Temperli. Die Benutzung der Applikation';
            replaceStringInFile(`${wtjFolder}/html/info.html`, oldLinkText, newLinkText);

            // (7) info-box 5
            oldLinkText = 'info-copyright-descr';
            newLinkText = '';
            replaceStringInFile(`${wtjFolder}/html/info.html`, oldLinkText, newLinkText);

            // (7) info-box 6
            oldLinkText = '<!-- TODO licenses for MIT should included be here -->';
            newLinkText = '    <li><strong>Diff-Match-Patch:</strong> <a href="https://github.com/google/diff-match-patch" target="_blank" rel="noopener">https://github.com/google/diff-match-patch</a> (Apache License 2.0)';
            newLinkText += '\n    <!-- TODO licenses for MIT should included be here -->';
            replaceStringInFile(`${wtjFolder}/html/info.html`, oldLinkText, newLinkText);

            // (8) logo
            oldLinkText = '<li id="logo-name">WebTigerJython</li>';
            newLinkText = '<li id="logo-name">WebTigerJython</li><li id="logo-v2" title="Cloud-Version by Beat Temperli">CLOUD</li>';
            htmlWTJ = htmlWTJ.replace(oldLinkText, newLinkText);

            // (9) favicon
            oldLinkText = '<link rel="icon" href="img/favicon.ico">';
            newLinkText = '<link rel="icon" href="/assets/images/favicon.png">';
            htmlWTJ = htmlWTJ.replace(oldLinkText, newLinkText);

            // end: update html
            const googleAnalytics = "<!-- Google tag (gtag.js) -->\n" +
                "<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-HJR10WMWYN\"></script>\n" +
                "<script>\n" +
                "  window.dataLayer = window.dataLayer || [];\n" +
                "  function gtag(){dataLayer.push(arguments);}\n" +
                "  gtag('js', new Date());\n" +
                "\n" +
                "  gtag('config', 'G-HJR10WMWYN');\n" +
                "</script>";

            htmlWTJ = htmlWTJ.replace('</head>', `{% include '../include/wtj-head.twig' %}\n${googleAnalytics}\n</head>`);
            htmlWTJ = htmlWTJ.replace('<body>', "<body>\n{% include '../include/wtj-first.twig' %}");
            htmlWTJ = htmlWTJ.replace('</body>', "{% include '../include/wtj-last.twig' %}\n</body>");
        }).then(() => {
            fs.writeFile(wtjTwig, htmlWTJ, (err) => {
                if (err) {
                    console.error('Error creating file:', wtjTwig, err);
                } else {
                    console.log(`Twigfile ${wtjTwig} successfully created!`);
                }
            });
        }).then(() => {
            console.log('successfully updated wtj!');
        });
    });
})();
