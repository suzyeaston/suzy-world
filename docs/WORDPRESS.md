# WordPress setup

After plugin activation:

1. Open `Tools → SUZY//WORLD`.
2. Import `samples/albums.csv` only after replacing the example row with real data.
3. Leave imports as drafts while testing.
4. Verify the public API:
   - `/wp-json/suzy-world/v1/schema`
   - `/wp-json/suzy-world/v1/albums`
5. Create a WordPress page such as **Music World**.
6. Add:
   `[suzy_world_albums]`

The SUZY//AI project page and the album-library page can remain separate.
