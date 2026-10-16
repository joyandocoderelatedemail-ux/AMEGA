{{--
    Shared by the BI print pages: long bond paper (8.5 x 13 in, the size the BI
    forms are laid out on), the screen-only toolbar, and the form's boxes.
    The Bagong Pilipinas mark prints when its file is present in
    public/images/logoapproved/bagong-pilipinas.png.
--}}
<style>
    @page { size: 8.5in 13in; margin: 0; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #E9EDF3;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
    }

    .sheet {
        width: 8.5in;
        height: 13in;
        margin: 18px auto;
        background: #fff;
        box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
        position: relative;
        overflow: hidden;
    }

    .serif { font-family: 'Book Antiqua', Palatino, 'Palatino Linotype', 'Times New Roman', serif; }

    /* The BI masthead: seal, office name, Bagong Pilipinas mark */
    .masthead { display: flex; align-items: center; justify-content: center; }
    .masthead .seal, .masthead .mark { flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
    .masthead img { display: block; width: 100%; height: 100%; object-fit: contain; }
    .masthead .office { text-align: center; line-height: 1.15; }

    /* A tick box, with the tick when the item applies */
    .box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #000;
        background: #fff;
        vertical-align: middle;
        font-weight: bold;
        line-height: 1;
    }

    /* Filled-in text sits in the form's boxes in capitals, as the BI asks */
    .fill { text-transform: uppercase; font-family: Arial, Helvetica, sans-serif; font-weight: bold; color: #0b2a6b; }

    /* Screen-only toolbar */
    .toolbar {
        width: 8.5in;
        margin: 18px auto 0;
        display: flex;
        gap: 10px;
        align-items: center;
        font-family: Arial, Helvetica, sans-serif;
    }
    .toolbar a, .toolbar button {
        font-family: inherit;
        font-size: 12px;
        font-weight: bold;
        padding: 9px 18px;
        border-radius: 8px;
        border: 1px solid #003B95;
        background: #003B95;
        color: #fff;
        cursor: pointer;
        text-decoration: none;
    }
    .toolbar a.ghost { background: #fff; color: #003B95; }
    .toolbar .hint { font-size: 12px; color: #40506B; margin-left: auto; }

    @media print {
        body { background: #fff; }
        .sheet { margin: 0; box-shadow: none; }
        .toolbar { display: none; }
        .fill { color: #000; }
    }

    @media (max-width: 900px) {
        .toolbar { width: auto; padding: 0 12px; flex-wrap: wrap; }
    }
</style>
