import { defineCollections, defineConfig } from 'fumadocs-mdx/config';
import { z } from 'zod';

export const docs = defineCollections({
  type: 'doc',
  dir: 'content/docs',
  schema: z.object({
    title: z.string(),
    description: z.string().optional(),
    icon: z.string().optional(),
  }),
});

export default defineConfig({
  collections: [docs],
});
