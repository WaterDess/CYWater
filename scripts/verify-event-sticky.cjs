const { chromium } = require(process.env.CYWATER_NODE_MODULES + '/playwright');
const fs = require('node:fs');
(async () => {
 const browser = await chromium.launch({channel:'chrome',headless:true});
 const page = await browser.newPage();
 if (process.env.CYWATER_PREVIEW === '1') {
  await page.route('**/assets/css/pages.css*', r => r.fulfill({contentType:'text/css',body:fs.readFileSync('wordpress/wp-content/themes/cywater/assets/css/pages.css','utf8')}));
 }
 for (const width of [1440,390]) {
  await page.setViewportSize({width,height:900});
  await page.goto('https://cywater.org/events/',{waitUntil:'networkidle'});
  if (process.env.CYWATER_PREVIEW === '1') await page.locator('.event-index-nav').evaluate(e=>{e.removeAttribute('data-reveal');e.style.opacity='1';e.style.transform='none';});
  for(const y of [1400,2100]) {
   await page.evaluate(y=>window.scrollTo(0,y),y);
   await page.waitForTimeout(250);
   const result=await page.locator('.event-index-nav').evaluate(e=>({top:e.getBoundingClientRect().top,position:getComputedStyle(e).position,overflow:document.documentElement.scrollWidth>innerWidth}));
   console.log(JSON.stringify({width,y,...result}));
   if(result.position!=='sticky'||Math.abs(result.top-(width>1024?104:72))>2||result.overflow) throw Error('Sticky navigation failed');
  }
  await page.locator('.event-index-nav a[href="#annual-meetings"]').click();
  await page.waitForTimeout(1000);
  const heading=await page.locator('#annual-meetings').boundingBox();
  if(heading.y<72) throw Error('Heading obscured');
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
