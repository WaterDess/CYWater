const {chromium}=require(process.env.CYWATER_NODE_MODULES+'/playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  const page=await browser.newPage();
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   await page.goto('https://cywater.org/news/',{waitUntil:'networkidle'});
   const card=page.locator('#opportunities .news-feature');
   await card.scrollIntoViewIfNeeded();
   await page.waitForTimeout(700);
   if(!await card.innerText().then(t=>t.includes('UMich PhD')))throw Error('Recruitment missing');
   if(await page.locator('#spotlights a[href*="umich-phd"]').count())throw Error('Duplicated spotlight');
   const result=await card.evaluate(e=>{
    const img=e.querySelector('img'),box=e.getBoundingClientRect(),ib=img.getBoundingClientRect();
    return {imageLoaded:img.complete&&img.naturalWidth>0,bottomGap:box.bottom-ib.bottom,overflow:document.documentElement.scrollWidth>innerWidth};
   });
   if(!result.imageLoaded||result.overflow||(width>720&&Math.abs(result.bottomGap)>2))throw Error(JSON.stringify(result));
   console.log(JSON.stringify({width,...result}));
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
