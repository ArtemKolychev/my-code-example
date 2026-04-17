#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// ─── Config from env ───────────────────────────────────────────────────────
const GITLAB_HOST = process.env.GITLAB_HOST?.replace(/\/$/, "") ?? "https://gitlab.com";
const GITLAB_TOKEN = process.env.GITLAB_TOKEN ?? "";

if (!GITLAB_TOKEN) {
  process.stderr.write("Error: GITLAB_TOKEN env variable is required\n");
  process.exit(1);
}

// ─── API helper ────────────────────────────────────────────────────────────
async function gitlabRequest<T>(
  path: string,
  method: string = "GET",
  body?: unknown,
  params?: Record<string, string | number | boolean>
): Promise<T> {
  const url = new URL(`${GITLAB_HOST}/api/v4${path}`);
  if (params) {
    for (const [k, v] of Object.entries(params)) {
      url.searchParams.set(k, String(v));
    }
  }

  const res = await fetch(url.toString(), {
    method,
    headers: {
      "PRIVATE-TOKEN": GITLAB_TOKEN,
      "Content-Type": "application/json",
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`GitLab API ${res.status}: ${text}`);
  }

  return res.json() as Promise<T>;
}

function encodeProject(project: string): string {
  return encodeURIComponent(project);
}

// ─── Server ────────────────────────────────────────────────────────────────
const server = new McpServer({
  name: "gitlab-mcp-server",
  version: "1.0.0",
});

// ─── Tool: list issues ─────────────────────────────────────────────────────
server.registerTool(
  "gitlab_list_issues",
  {
    title: "List GitLab Issues",
    description: `List issues in a GitLab project with optional filters.

Args:
  - project (string): Project path, e.g. "mygroup/myrepo"
  - state ('opened' | 'closed' | 'all'): Filter by state (default: 'opened')
  - assignee (string, optional): Filter by assignee username
  - milestone (string, optional): Filter by milestone title
  - labels (string, optional): Comma-separated label names
  - search (string, optional): Search in title and description
  - page (number): Page number (default: 1)
  - per_page (number): Items per page, max 100 (default: 20)

Returns: List of issues with id, iid, title, state, assignee, labels, milestone, dates.`,
    inputSchema: z.object({
      project: z.string().describe("Project path, e.g. 'group/repo'"),
      state: z.enum(["opened", "closed", "all"]).default("opened"),
      assignee: z.string().optional().describe("Filter by assignee username"),
      milestone: z.string().optional().describe("Filter by milestone title"),
      labels: z.string().optional().describe("Comma-separated label names"),
      search: z.string().optional().describe("Search in title and description"),
      page: z.number().int().min(1).default(1),
      per_page: z.number().int().min(1).max(100).default(20),
    }),
    annotations: {
      readOnlyHint: true,
      destructiveHint: false,
      idempotentHint: true,
      openWorldHint: true,
    },
  },
  async (params) => {
    const qp: Record<string, string | number | boolean> = {
      state: params.state,
      page: params.page,
      per_page: params.per_page,
    };
    if (params.assignee) qp["assignee_username"] = params.assignee;
    if (params.milestone) qp["milestone"] = params.milestone;
    if (params.labels) qp["labels"] = params.labels;
    if (params.search) qp["search"] = params.search;

    const issues = await gitlabRequest<IssueResponse[]>(
      `/projects/${encodeProject(params.project)}/issues`,
      "GET",
      undefined,
      qp
    );

    if (issues.length === 0) {
      return { content: [{ type: "text", text: "No issues found." }] };
    }

    const text = issues
      .map(
        (i) =>
          `#${i.iid} [${i.state}] ${i.title}\n` +
          `  Assignee: ${i.assignee?.username ?? "—"} | Labels: ${i.labels.join(", ") || "—"} | Milestone: ${i.milestone?.title ?? "—"}\n` +
          `  Created: ${i.created_at.slice(0, 10)} | URL: ${i.web_url}`
      )
      .join("\n\n");

    return { content: [{ type: "text", text }] };
  }
);

// ─── Tool: get issue ───────────────────────────────────────────────────────
server.registerTool(
  "gitlab_get_issue",
  {
    title: "Get GitLab Issue",
    description: `Get full details of a single GitLab issue including description.

Args:
  - project (string): Project path, e.g. "mygroup/myrepo"
  - iid (number): Issue internal ID (the #number shown in GitLab UI)

Returns: Full issue details including description, comments count, labels, milestone, assignees.`,
    inputSchema: z.object({
      project: z.string().describe("Project path, e.g. 'group/repo'"),
      iid: z.number().int().min(1).describe("Issue IID (the #number in GitLab)"),
    }),
    annotations: {
      readOnlyHint: true,
      destructiveHint: false,
      idempotentHint: true,
      openWorldHint: true,
    },
  },
  async (params) => {
    const issue = await gitlabRequest<IssueResponse>(
      `/projects/${encodeProject(params.project)}/issues/${params.iid}`
    );

    const text =
      `#${issue.iid}: ${issue.title}\n` +
      `State: ${issue.state} | Author: ${issue.author.username}\n` +
      `Assignee: ${issue.assignee?.username ?? "—"}\n` +
      `Labels: ${issue.labels.join(", ") || "—"}\n` +
      `Milestone: ${issue.milestone?.title ?? "—"}\n` +
      `Created: ${issue.created_at.slice(0, 10)} | Updated: ${issue.updated_at.slice(0, 10)}\n` +
      `Comments: ${issue.user_notes_count}\n` +
      `URL: ${issue.web_url}\n\n` +
      `--- Description ---\n${issue.description || "(no description)"}`;

    return { content: [{ type: "text", text }] };
  }
);

// ─── Tool: create issue ────────────────────────────────────────────────────
server.registerTool(
  "gitlab_create_issue",
  {
    title: "Create GitLab Issue",
    description: `Create a new issue in a GitLab project.

Args:
  - project (string): Project path, e.g. "mygroup/myrepo"
  - title (string): Issue title
  - description (string, optional): Issue body in Markdown
  - labels (string, optional): Comma-separated label names
  - assignee (string, optional): Assignee username
  - milestone_id (number, optional): Milestone ID

Returns: Created issue with iid and URL.`,
    inputSchema: z.object({
      project: z.string().describe("Project path"),
      title: z.string().min(1).max(255).describe("Issue title"),
      description: z.string().optional().describe("Issue body in Markdown"),
      labels: z.string().optional().describe("Comma-separated label names"),
      assignee_username: z.string().optional().describe("Assignee username"),
      milestone_id: z.number().int().optional().describe("Milestone ID"),
    }),
    annotations: {
      readOnlyHint: false,
      destructiveHint: false,
      idempotentHint: false,
      openWorldHint: true,
    },
  },
  async (params) => {
    const body: Record<string, unknown> = { title: params.title };
    if (params.description) body["description"] = params.description;
    if (params.labels) body["labels"] = params.labels;
    if (params.milestone_id) body["milestone_id"] = params.milestone_id;

    // Resolve assignee username -> id
    if (params.assignee_username) {
      try {
        const users = await gitlabRequest<{ id: number }[]>(
          "/users",
          "GET",
          undefined,
          { username: params.assignee_username }
        );
        if (users.length > 0) body["assignee_ids"] = [users[0].id];
      } catch {
        // ignore, create without assignee
      }
    }

    const issue = await gitlabRequest<IssueResponse>(
      `/projects/${encodeProject(params.project)}/issues`,
      "POST",
      body
    );

    return {
      content: [
        {
          type: "text",
          text: `✓ Created issue #${issue.iid}: ${issue.title}\nURL: ${issue.web_url}`,
        },
      ],
    };
  }
);

// ─── Tool: update issue ────────────────────────────────────────────────────
server.registerTool(
  "gitlab_update_issue",
  {
    title: "Update GitLab Issue",
    description: `Update an existing GitLab issue (title, description, state, labels, assignee, milestone).

Args:
  - project (string): Project path
  - iid (number): Issue IID
  - title (string, optional): New title
  - description (string, optional): New description
  - state_event ('close' | 'reopen', optional): Close or reopen issue
  - labels (string, optional): New comma-separated labels (replaces all existing)
  - add_labels (string, optional): Labels to add
  - remove_labels (string, optional): Labels to remove
  - assignee_username (string, optional): New assignee (empty string to unassign)
  - milestone_id (number, optional): New milestone ID (0 to remove)

Returns: Updated issue summary.`,
    inputSchema: z.object({
      project: z.string(),
      iid: z.number().int().min(1),
      title: z.string().optional(),
      description: z.string().optional(),
      state_event: z.enum(["close", "reopen"]).optional(),
      labels: z.string().optional(),
      add_labels: z.string().optional(),
      remove_labels: z.string().optional(),
      assignee_username: z.string().optional(),
      milestone_id: z.number().int().optional(),
    }),
    annotations: {
      readOnlyHint: false,
      destructiveHint: false,
      idempotentHint: true,
      openWorldHint: true,
    },
  },
  async (params) => {
    const body: Record<string, unknown> = {};
    if (params.title) body["title"] = params.title;
    if (params.description !== undefined) body["description"] = params.description;
    if (params.state_event) body["state_event"] = params.state_event;
    if (params.labels !== undefined) body["labels"] = params.labels;
    if (params.add_labels) body["add_labels"] = params.add_labels;
    if (params.remove_labels) body["remove_labels"] = params.remove_labels;
    if (params.milestone_id !== undefined) body["milestone_id"] = params.milestone_id;

    if (params.assignee_username !== undefined) {
      if (params.assignee_username === "") {
        body["assignee_ids"] = [];
      } else {
        try {
          const users = await gitlabRequest<{ id: number }[]>(
            "/users",
            "GET",
            undefined,
            { username: params.assignee_username }
          );
          if (users.length > 0) body["assignee_ids"] = [users[0].id];
        } catch {
          // ignore
        }
      }
    }

    const issue = await gitlabRequest<IssueResponse>(
      `/projects/${encodeProject(params.project)}/issues/${params.iid}`,
      "PUT",
      body
    );

    return {
      content: [
        {
          type: "text",
          text: `✓ Updated issue #${issue.iid}: ${issue.title} [${issue.state}]\nURL: ${issue.web_url}`,
        },
      ],
    };
  }
);

// ─── Tool: list projects ───────────────────────────────────────────────────
server.registerTool(
  "gitlab_list_projects",
  {
    title: "List GitLab Projects",
    description: `List GitLab projects accessible with the current token.

Args:
  - search (string, optional): Filter projects by name
  - membership (boolean): Only show projects you're a member of (default: true)
  - per_page (number): Items per page (default: 20)

Returns: List of projects with path_with_namespace and URLs.`,
    inputSchema: z.object({
      search: z.string().optional(),
      membership: z.boolean().default(true),
      per_page: z.number().int().min(1).max(100).default(20),
    }),
    annotations: {
      readOnlyHint: true,
      destructiveHint: false,
      idempotentHint: true,
      openWorldHint: true,
    },
  },
  async (params) => {
    const qp: Record<string, string | number | boolean> = {
      membership: params.membership,
      per_page: params.per_page,
      order_by: "last_activity_at",
    };
    if (params.search) qp["search"] = params.search;

    const projects = await gitlabRequest<ProjectResponse[]>("/projects", "GET", undefined, qp);

    if (projects.length === 0) {
      return { content: [{ type: "text", text: "No projects found." }] };
    }

    const text = projects
      .map((p) => `${p.path_with_namespace}\n  ${p.description ?? ""}\n  URL: ${p.web_url}`)
      .join("\n\n");

    return { content: [{ type: "text", text }] };
  }
);

// ─── Types ─────────────────────────────────────────────────────────────────
interface IssueResponse {
  id: number;
  iid: number;
  title: string;
  description: string | null;
  state: string;
  author: { username: string };
  assignee?: { username: string };
  labels: string[];
  milestone?: { title: string };
  created_at: string;
  updated_at: string;
  user_notes_count: number;
  web_url: string;
}

interface ProjectResponse {
  id: number;
  path_with_namespace: string;
  description: string | null;
  web_url: string;
}

// ─── Start ─────────────────────────────────────────────────────────────────
async function main(): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  process.stderr.write("GitLab MCP server running (stdio)\n");
}

main().catch((err) => {
  process.stderr.write(`Fatal: ${err}\n`);
  process.exit(1);
});
