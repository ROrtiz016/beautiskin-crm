import type { NextConfig } from "next";

const laravelTarget = (process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000").replace(
  /\/$/,
  "",
);

const nextConfig: NextConfig = {
  turbopack: {
    root: process.cwd(),
  },
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
