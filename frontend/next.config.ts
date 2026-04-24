import type { NextConfig } from "next";

const laravelTarget = (process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000").replace(
  /\/$/,
  "",
);

/** Docker Desktop on Windows (bind mount): enable Turbopack/webpack polling so HMR sees saves. */
const devFilePolling =
  process.env.NEXT_DEV_POLLING === "1" || process.env.WATCHPACK_POLLING === "true";

const nextConfig: NextConfig = {
  turbopack: {
    root: process.cwd(),
  },
  ...(devFilePolling ? { watchOptions: { pollIntervalMs: 1000 } } : {}),
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${laravelTarget}/api/:path*`,
      },
    ];
  },
};

export default nextConfig;
