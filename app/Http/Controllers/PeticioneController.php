<?php

namespace App\Http\Controllers;

use App\Models\Peticione;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeticioneController extends Controller
{
    public function index()
    {
        return response()->json(Peticione::all(), 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|max:255',
            'descripcion' => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categorias,id',
            'imagen' => 'nullable|image|max:2048'
        ]);

        $data = $request->all();


        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('peticiones', 'public');
            $data['imagen'] = $path;
        } else {
            $data['imagen'] = '';
        }

        $peticion = new Peticione($data);
        $peticion->user_id = Auth::id();
        $peticion->firmantes = 0;
        $peticion->estado = 'pendiente';
        $peticion->save();

        return response()->json($peticion, 201);
    }


    public function show($id)
    {
        $peticione = Peticione::findOrFail($id);
        return response()->json($peticione, 200);
    }

    public function update(Request $request, $id)
    {
        $peticione = Peticione::findOrFail($id);

        // Extrae únicamente los campos que deseas actualizar
        $data = $request->only(['titulo', 'descripcion', 'destinatario', 'categoria_id']);

        // Si se ha enviado una nueva imagen, la procesa y actualiza la ruta
        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('peticiones', 'public');
            $data['imagen'] = $path;
        }

        $peticione->update($data);

        return response()->json($peticione, 200);
    }


    public function listmine(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        $misPeticiones = $user->peticiones()->get();
        return response()->json($misPeticiones, 200);
    }



    public function delete($id)
    {
        $peticione = Peticione::findOrFail($id);
        $this->authorize('delete', $peticione);

        $peticione->delete();
        return response()->json(['message' => 'Petición eliminada'], 200);
    }

    public function firmar($id)
    {
        $peticione = Peticione::findOrFail($id);
        $this->authorize('firmar', $peticione);

        $peticione->firmantes += 1;
        $peticione->save();

        return response()->json($peticione, 200);
    }



    public function cambiarEstado($id)
    {
        $peticione = Peticione::findOrFail($id);
        $this->authorize('cambiarEstado', $peticione);

        $peticione->estado = 'aceptada';
        $peticione->save();

        return response()->json($peticione, 200);
    }
}
