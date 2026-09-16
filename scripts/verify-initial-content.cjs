const {chromium}=require(process.env.CYWATER_NODE_MODULES+'/playwright');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const js of [true,false])for(const width of [1440,390]){
   const page=await browser.newPage({javaScriptEnabled:js,viewport:{width,height:1000}});
   if(process.env.CYWATER_PREVIEW==='1')for(const asset of ['css/base.css','js/main.js'])await page.route('**/assets/'+asset+'*',r=>r.fulfill({contentType:asset.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync('wordpress/wp-content/themes/cywater/assets/'+asset,'utf8')}));
   await page.goto('https://cywater.org/umich-phd-and-postdoctoral-opportunities-for-2027/',{waitUntil:'networkidle'});
   const body=await page.locator('.entry-content').evaluate(e=>({opacity:getComputedStyle(e).opacity,text:e.textContent.trim().length,pending:e.classList.contains('reveal-pending')}));
   if(body.opacity!=='1'||body.text<100||body.pending)throw Error(JSON.stringify(body));
   await page.goto('https://cywater.org/news/',{waitUntil:'networkidle'});
   const hidden=await page.locator('[data-reveal]').evaluateAll(els=>els.filter((e,i)=>(i<3||e.getBoundingClientRect().top<innerHeight)&&getComputedStyle(e).opacity==='0').length);
   if(hidden)throw Error('Opening content hidden');
   console.log(JSON.stringify({js,width,articleVisible:true,openingVisible:true}));
   await page.close();
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
