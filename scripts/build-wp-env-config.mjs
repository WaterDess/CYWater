import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { parseEnv } from "node:util";

const root = process.cwd();
const basePath = path.join(root, ".wp-env.json");
const outputPath = path.join(root, "wordpress", "runtime", "wp-env.local.json");
const base = JSON.parse(await readFile(basePath, "utf8"));

let local = {};
try {
  local = parseEnv(await readFile(path.join(root, ".env"), "utf8"));
} catch (error) {
  if (error.code !== "ENOENT") throw error;
}

const allowed = [
  "CYWATER_PAYMENT_MODE",
  "CYWATER_ALLOW_LIVE_PAYMENTS",
  "CYWATER_MAIL_TRANSPORT",
  "CYWATER_ENABLE_CALENDAR_YEAR_END",
  "MAILPIT_HOST",
  "MAILPIT_PORT",
  "STRIPE_PUBLISHABLE_KEY",
  "STRIPE_SECRET_KEY",
  "STRIPE_WEBHOOK_SECRET",
  "SMTP_HOST",
  "SMTP_PORT",
  "SMTP_USERNAME",
  "SMTP_PASSWORD",
  "SMTP_FROM_ADDRESS",
  "SMTP_FROM_NAME",
];

for (const key of allowed) {
  const value = process.env[key] || local[key];
  if (value !== undefined && value !== "") {
    base.config[key] = value;
  }
}

await mkdir(path.dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(base, null, 2)}\n`, {
  encoding: "utf8",
  mode: 0o600,
});
console.log("Generated ignored wp-env runtime configuration.");
