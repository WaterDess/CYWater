import { spawn } from "node:child_process";
import { mkdtemp, mkdir, rm, writeFile } from "node:fs/promises";
import os from "node:os";
import path from "node:path";

function option(name, fallback) {
  const index = process.argv.indexOf(`--${name}`);
  return index >= 0 ? process.argv[index + 1] : fallback;
}

const url = option("url", "http://127.0.0.1:8017/membership/");
const selector = option("selector", "body");
const output = path.resolve(option("output", "artifacts/visual-qa/capture.png"));
const width = Number(option("width", "1440"));
const height = Number(option("height", "900"));
const port = Number(option("port", "9333"));
const chromeCandidates = [
  process.env.CHROME_PATH,
  "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
  "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
].filter(Boolean);
const chromePath = chromeCandidates[0];

if (!chromePath) throw new Error("Chrome executable was not found.");
if (!Number.isFinite(width) || !Number.isFinite(height)) throw new Error("Invalid viewport dimensions.");

const profile = await mkdtemp(path.join(os.tmpdir(), "cywater-chrome-"));
const chrome = spawn(
  chromePath,
  [
    "--headless=new",
    "--disable-gpu",
    "--hide-scrollbars",
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    "about:blank",
  ],
  { stdio: "ignore" }
);

function delay(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function devtools(pathname, init) {
  const response = await fetch(`http://127.0.0.1:${port}${pathname}`, init);
  if (!response.ok) throw new Error(`DevTools request failed: ${response.status}`);
  return response.json();
}

async function waitForDevtools() {
  for (let attempt = 0; attempt < 50; attempt += 1) {
    try {
      return await devtools("/json/version");
    } catch {
      await delay(100);
    }
  }
  throw new Error("Chrome DevTools did not become available.");
}

await waitForDevtools();
const target = await devtools(`/json/new?${encodeURIComponent(url)}`, { method: "PUT" });
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
  socket.addEventListener("open", resolve, { once: true });
  socket.addEventListener("error", reject, { once: true });
});

let commandId = 0;
const pending = new Map();
socket.addEventListener("message", (event) => {
  const message = JSON.parse(event.data);
  if (!message.id || !pending.has(message.id)) return;
  const { resolve, reject } = pending.get(message.id);
  pending.delete(message.id);
  if (message.error) reject(new Error(message.error.message));
  else resolve(message.result);
});

function command(method, params = {}) {
  commandId += 1;
  const id = commandId;
  return new Promise((resolve, reject) => {
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
  });
}

try {
  await command("Page.enable");
  await command("Runtime.enable");
  await command("Emulation.setDeviceMetricsOverride", {
    width,
    height,
    deviceScaleFactor: 1,
    mobile: false,
  });
  await command("Page.navigate", { url });

  for (let attempt = 0; attempt < 80; attempt += 1) {
    const state = await command("Runtime.evaluate", {
      expression: "document.readyState",
      returnByValue: true,
    });
    if (state.result.value === "complete") break;
    await delay(100);
  }

  const prepared = await command("Runtime.evaluate", {
    expression: `
      (async () => {
        await document.fonts.ready;
        await Promise.all([...document.images].map((image) => {
          if (image.complete) return Promise.resolve();
          return new Promise((resolve) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
          });
        }));
        document.documentElement.style.scrollBehavior = 'auto';
        document.querySelectorAll('[data-reveal]').forEach((node) => node.classList.add('is-visible'));
        const node = document.querySelector(${JSON.stringify(selector)});
        if (!node) return false;
        const position = () => {
          const rect = node.getBoundingClientRect();
          const target = window.scrollY + rect.top - Math.max(72, (window.innerHeight - rect.height) / 2);
          window.scrollTo(0, Math.max(0, target));
        };
        position();
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        position();
        const rect = node.getBoundingClientRect();
        return {
          scrollY: window.scrollY,
          targetTop: rect.top,
          targetHeight: rect.height,
          viewportHeight: window.innerHeight,
        };
      })()
    `,
    awaitPromise: true,
    returnByValue: true,
  });
  if (!prepared.result.value) throw new Error(`Selector not found: ${selector}`);
  await delay(350);
  console.log(JSON.stringify(prepared.result.value));

  const screenshot = await command("Page.captureScreenshot", {
    format: "png",
    captureBeyondViewport: false,
    fromSurface: true,
  });
  await mkdir(path.dirname(output), { recursive: true });
  await writeFile(output, Buffer.from(screenshot.data, "base64"));
  console.log(output);
} finally {
  socket.close();
  chrome.kill();
  await Promise.race([
    new Promise((resolve) => chrome.once("exit", resolve)),
    delay(2_000),
  ]);
  for (let attempt = 0; attempt < 5; attempt += 1) {
    try {
      await rm(profile, { recursive: true, force: true });
      break;
    } catch (error) {
      if (attempt === 4) console.warn(`Temporary Chrome profile was not removed: ${error.message}`);
      else await delay(250);
    }
  }
}
