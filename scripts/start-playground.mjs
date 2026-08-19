import { runCLI } from "@wp-playground/cli";

const mounts = [
  ["./wordpress/wp-content/themes/cywater", "/wordpress/wp-content/themes/cywater"],
  ["./wordpress/wp-content/plugins/cywater-core", "/wordpress/wp-content/plugins/cywater-core"],
  ["./wordpress/wp-content/plugins/cywater-membership", "/wordpress/wp-content/plugins/cywater-membership"],
  ["./wordpress/wp-content/plugins/cywater-environment", "/wordpress/wp-content/plugins/cywater-environment"],
].map(([hostPath, vfsPath]) => ({ hostPath, vfsPath }));

const server = await runCLI({
  command: "server",
  port: 8890,
  php: "8.3",
  wp: "7.0.2",
  debug: true,
  "mount-before-install": mounts,
  blueprint: "./wordpress/blueprint.json",
});

console.log(`CYWater Playground is running at ${server.serverUrl}`);

const stop = async () => {
  await server[Symbol.asyncDispose]();
  process.exit(0);
};

process.on("SIGINT", stop);
process.on("SIGTERM", stop);

await new Promise(() => {});
