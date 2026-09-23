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
   // Synthetic fixtures must never contact a real application endpoint, even
   // if a regression accidentally reintroduces native form submission.
   await page.route('**/*',route=>route.abort());
   for(const state of ['logged-out','preview','form','confirmation','interactive-preview']) {
    assert.ok(typeof renders[state]==='string' && renders[state].length,`Missing rendered fixture: ${state}`);
    await page.setContent('<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style></head><body><main class="section"><div class="container container-narrow"><div class="prose entry-content">'+renders[state]+'</div></div></main></body></html>');
    if(js) await page.addScriptTag({content:fs.readFileSync(path.join(plugin,'assets/best-paper.js'),'utf8')});
    assert.equal(await page.locator('.cywater-best-paper').count(),1);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Overflow ${state}/${width}/${js}`);
    if(state==='preview')assert.equal(await page.locator('button[type=submit]').isDisabled(),true);
    if(state==='logged-out')assert.equal(await page.locator('form').count(),0);
    if(state==='form' || state==='interactive-preview'){
     if(state==='form')assert.equal(await page.locator('button[type=submit]').isEnabled(),true);
     if(state==='interactive-preview'){
      assert.equal(await page.locator('form').count(),0,'Interactive preview contains an HTML form');
      assert.equal(await page.locator('[action],input[name=action],input[name=award_id],input[name=cywater_best_paper_nonce]').count(),0,'Interactive preview exposes submission wiring');
      assert.equal(await page.locator('button[type=submit],input[type=submit]').count(),0,'Interactive preview exposes a submit control');
      assert.equal(await page.locator('.cywater-best-paper__actions button[type=button]').isDisabled(),true);
      for(const name of ['first_name','last_name','email','institution','dob']){
       assert.equal(await page.locator(`input[name=${name}]`).isEnabled(),true,`Preview ${name} is disabled`);
      }
      assert.ok((await page.locator('input[name=institution]').inputValue()).length,'Account institution was not prefilled');
      assert.equal(await page.locator('input[name=dob]').inputValue(),'','Preview guessed a date of birth');
      await page.locator('input[name=first_name]').fill('Preview applicant');
      await page.locator('input[name=email]').fill('preview@example.invalid');
      await page.locator('input[name=institution]').fill('Preview institution');
      await page.locator('input[name=dob]').fill('1995-01-01');
      const requests=[];
      const navigations=[];
      const recordRequest=request=>requests.push(request.url());
      const recordNavigation=frame=>{if(frame===page.mainFrame())navigations.push(frame.url());};
      const beforeUrl=page.url();
      page.on('request',recordRequest);
      page.on('framenavigated',recordNavigation);
      try{
       await page.locator('input[name=first_name]').press('Enter');
       await page.locator('input[name=email]').press('Enter');
       await page.waitForTimeout(100);
       assert.deepEqual(requests,[],`Preview Enter generated a network request (${js})`);
       assert.deepEqual(navigations,[],`Preview Enter navigated (${js})`);
       assert.equal(page.url(),beforeUrl);
      }finally{
       page.off('request',recordRequest);
       page.off('framenavigated',recordNavigation);
      }
      await page.locator('input[name=institution]').focus();
      assert.notEqual(await page.locator('input[name=institution]').evaluate(e=>getComputedStyle(e).outlineStyle),'none','Preview text field missing focus style');
      for(const checkbox of await page.locator('input[type=checkbox]').all()){
       assert.equal(await checkbox.isEnabled(),true,'Preview declaration is disabled');
       await checkbox.check();
       assert.equal(await checkbox.isChecked(),true);
       await checkbox.uncheck();
      }
     }
     const input=page.locator('input[type=file]').first();
     const label=page.locator('.cywater-best-paper__file-button').first();
     assert.equal(await input.isEnabled(),true,'File picker is disabled');
     await label.scrollIntoViewIfNeeded();
     const hit=await label.evaluate(e=>{const b=e.getBoundingClientRect();return document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)===e;});
     assert.ok(hit,'Choose File hit target obscured');
     await page.keyboard.press('Tab');
     await input.focus();
     assert.notEqual(await label.evaluate(e=>getComputedStyle(e).outlineStyle),'none','File control missing keyboard focus');
     const chooser=page.waitForEvent('filechooser');
     await label.click();
     await (await chooser).setFiles({name:'sample-paper.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n%%EOF')});
     assert.equal(await input.evaluate(e=>e.files[0].name),'sample-paper.pdf','Selected file was not retained locally');
     if(js){
      assert.ok((await page.locator('[data-cywater-best-paper-filename]').first().textContent()).includes('sample-paper.pdf'));
     }
     if(js && state==='form'){await page.locator('.cywater-best-paper__file-field').first().screenshot({path:`artifacts/best-paper-upload-${width}.png`});}
     if(state==='interactive-preview'){
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Interactive preview overflow after edits/${width}/${js}`);
      if(js)await page.screenshot({path:`artifacts/best-paper-interactive-preview-${width}.png`,fullPage:true});
     }
    }
    if(state==='confirmation')assert.equal(await page.getByRole('link',{name:/Download/}).count(),2);
    if(js && state==='preview'){await page.screenshot({path:`artifacts/best-paper-preview-${width}.png`,fullPage:true});}
   }
   console.log(JSON.stringify({width,js,states:5,overflow:false,privateFilesVisibleToOwner:true,interactivePreviewNoSubmission:true}));
   await page.close();
  }
 } finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
