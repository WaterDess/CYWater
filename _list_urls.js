// 收集所有曾经用到的 Unsplash photo-id，逐个测试 HTTP 状态
const urls = [
  ["1541252260730-0412e8e2108e", "groundwater research (home/news/article)"],
  ["1521587760476-6c12a4b040da", "annual meeting event (home/news)"],
  ["1559825481-12a05cc00344", "mentorship (home/news)"],
  ["1497436072909-60f360e1d4b1", "award (news)"],
  ["1518770660439-4636190af475", "journal (news)"],
  ["1486325212027-8081e485255e", "travel grant (news)"],
  ["1502602898657-3e91760cbb34", "venue (event detail)"],
  ["1493514789931-586cb221d7a7", "river (about)"],
  ["1517245386807-bb43f82c33b4", "office (contact)"],
];
console.log("photo-id | status | context");
for (const [id, ctx] of urls) {
  console.log(id + " | ? | " + ctx);
}
