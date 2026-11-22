// Contract fixture shared by the SDK test suites. It mirrors the public ServerSMTP
// API shapes (apps/api/src/index.ts, services/dispatch.ts, services/mailSchedule.ts)
// without touching AWS or a database. Usage: node contract-server.mjs [port]
// Prints "listening <port>" when ready. GET /__last returns the last API request.
import { createServer } from "node:http";

export const TEST_KEY = "em_live_" + "a".repeat(48);
const KEY_PATTERN = /^[a-zA-Z0-9_.:-]{8,200}$/;

const job = (id, status = "scheduled") => ({
  id, status, sendAt: "2030-01-01T09:00:00.000Z", createdAt: "2029-12-31T09:00:00.000Z",
  updatedAt: "2029-12-31T09:00:00.000Z", attempts: 0, requestId: null, messageId: null, error: null,
});

function route(method, path, query, body, headers) {
  if (path.startsWith("/v1/")) {
    const token = headers.authorization?.match(/^Bearer (em_live_[a-f0-9]{48,64})$/i)?.[1];
    if (token !== TEST_KEY) return [401, { message: "Cheie API invalidă." }];
  }
  const needsKey = method === "POST" && ["/v1/send", "/v1/send/batch", "/v1/send/scheduled"].includes(path);
  if (needsKey && !KEY_PATTERN.test(headers["idempotency-key"] || "")) {
    return [400, { message: "Date invalide.", fields: [] }];
  }
  if (method === "POST" && path === "/v1/auth/check") return [200, { ok: true }];
  if (method === "POST" && path === "/v1/send") {
    if (body?.subject === "invalid") return [400, { message: "Date invalide.", fields: ["subject"] }];
    if (body?.to === "unknown@example.invalid") {
      return [202, { id: "req-2", requestId: "req-2", status: "unknown", accepted: [body.to], suppressed: [], reused: false }];
    }
    if (body?.to === "rejected@example.invalid") return [502, { message: "AWS SES a respins trimiterea. Cota a fost eliberată.", requestId: "req-3" }];
    const to = [].concat(body?.to ?? []);
    return [200, { id: "ses-1", requestId: "req-1", status: "sent", accepted: to, suppressed: [], reused: false }];
  }
  if (method === "POST" && path === "/v1/send/batch") {
    const results = (body?.messages ?? []).map((message, index) => ({
      index, httpStatus: 200, id: "ses-" + index, requestId: "req-" + index, status: "sent",
      accepted: [].concat(message.to), suppressed: [], reused: false,
    }));
    return [200, { results }];
  }
  if (method === "POST" && path === "/v1/send/scheduled") return [201, { ...job("job-1"), sendAt: body.sendAt }];
  if (method === "GET" && path === "/v1/send/scheduled") {
    return [200, { items: [job("job-1")], hasMore: false, limit: Number(query.get("limit") ?? 50), offset: Number(query.get("offset") ?? 0) }];
  }
  const match = path.match(/^\/v1\/send\/scheduled\/([^/]+)$/);
  if (match && method === "GET") return match[1] === "missing" ? [404, { message: "Mesaj programat inexistent." }] : [200, job(match[1])];
  if (match && method === "DELETE") return [200, job(match[1], "cancelled")];
  return [404, { message: "Not found" }];
}

export function startContractServer(port = 0) {
  let last = null;
  const server = createServer(async (request, response) => {
    const url = new URL(request.url, "http://localhost");
    if (url.pathname === "/__last") {
      response.writeHead(200, { "content-type": "application/json" }).end(JSON.stringify(last));
      return;
    }
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    const raw = Buffer.concat(chunks).toString();
    let body = null;
    try { body = raw ? JSON.parse(raw) : null; } catch { body = { __invalid: raw }; }
    last = {
      method: request.method, path: url.pathname, query: Object.fromEntries(url.searchParams),
      body, headers: {
        authorization: request.headers.authorization ?? null,
        "content-type": request.headers["content-type"] ?? null,
        "idempotency-key": request.headers["idempotency-key"] ?? null,
        "user-agent": request.headers["user-agent"] ?? null,
      },
    };
    const [status, payload] = route(request.method, url.pathname, url.searchParams, body, request.headers);
    response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(payload));
  });
  return new Promise((resolve) => server.listen(port, "127.0.0.1", () => resolve(server)));
}

if (process.argv[1]?.endsWith("contract-server.mjs")) {
  const server = await startContractServer(Number(process.argv[2] ?? 0));
  console.log("listening " + server.address().port);
}
