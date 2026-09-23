/* Visual/interaction QA of synthetic HTML rendered by the staging WP runtime. */
const { chromium } = require(process.env.CYWATER_NODE_MODULES + '/playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = process.cwd();
const theme = path.join(root, 'wordpress/wp-content/themes/cywater');
const plugin = path.join(root, 'wordpress/wp-content/plugins/cywater-best-paper');
const renders = JSON.parse(fs.readFileSync('artifacts/cywater-best-paper-render.json', 'utf8'));
const css = ['assets/css/base.css', 'assets/css/components.css', 'wordpress.css'].map(p => fs.readFileSync(path.join(theme,p),'utf8')).join('\n') + '\n' + fs.readFileSync(path.join(plugin,'assets/best-paper.css'),'utf8');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for (const width of [1440,390]) for (const js of [true,false]) {
   const page=await browser.newPage({viewport:{width,height:1000},javaScriptEnabled:js,reducedMotion:'reduce'});
   for(const state of ['logged-out','preview','form','confirmation']) {
    await page.setContent('<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style></head><body><main class="section"><div class="container container-narrow"><div class="prose entry-content">'+renders[state]+'</div></div></main></body></html>');
    if(js) await page.addScriptTag({content:fs.readFileSync(path.join(plugin,'assets/best-paper.js'),'utf8')});
    assert.equal(await page.locator('.cywater-best-paper').count(),1);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Overflow ${state}/${width}/${js}`);
    if(state==='preview')assert.equal(await page.locator('button[type=submit]').isDisabled(),true);
    if(state==='logged-out')assert.equal(await page.locator('form').count(),0);
    if(state==='form'){
     assert.equal(await page.locator('button[type=submit]').isEnabled(),true);
     const input=page.locator('input[type=file]').first();
     const label=page.locator('.cywater-best-paper__file-button').first();
     await label.scrollIntoViewIfNeeded();
     const hit=await label.evaluate(e=>{const b=e.getBoundingClientRect();return document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)===e;});
     assert.ok(hit,'Choose File hit target obscured');
     if(js){
      await input.focus();
      assert.notEqual(await label.evaluate(e=>getComputedStyle(e).outlineStyle),'none','File control missing keyboard focus');
      await input.setInputFiles({name:'sample-paper.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n%%EOF')});
      assert.ok((await page.locator('[data-cywater-best-paper-filename]').first().textContent()).includes('sample-paper.pdf'));
     }
     if(js){await page.locator('.cywater-best-paper__file-field').first().screenshot({path:`artifacts/best-paper-upload-${width}.png`});}
    }
    if(state==='confirmation')assert.equal(await page.getByRole('link',{name:/Download/}).count(),2);
    if(js && state==='preview'){await page.screenshot({path:`artifacts/best-paper-preview-${width}.png`,fullPage:true});}
   }
   console.log(JSON.stringify({width,js,states:4,overflow:false,privateFilesVisibleToOwner:true}));
   await page.close();
  }
 } finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
